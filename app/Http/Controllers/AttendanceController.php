<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LeaveCredit;
use App\Models\LeaveConfiguration;
use App\Models\LeaveRecord;
use App\Models\ActivityLog;
use App\Service\LeaveCreditComputationService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $computationService;

    public function __construct(LeaveCreditComputationService $computationService)
    {
        $this->computationService = $computationService;
    }
    public function upload(Request $request)
    {
        $request->validate([
            'file'       => 'required|file|mimes:xlsx,xls,csv',
            'month'      => 'required|integer|min:1|max:12',
            'year'       => 'required|integer',
            'department' => 'nullable|string', // no longer required — kept for backward compatibility with single-sheet uploads
        ]);

        $file = $request->file('file');
        $month = $request->month;
        $year = $request->year;

        $spreadsheet = IOFactory::load($file->getPathname());

        $sheets = $spreadsheet->getAllSheets();

        $results = [];
        $errors = [];
        $skipped = [];

        try {
            DB::transaction(function () use ($sheets, $month, $year, $request, &$results, &$errors, &$skipped) {
                $monthName = Carbon::create($year, $month, 1)->format('F');

                foreach ($sheets as $sheet) {
                    $sheetName = $sheet->getTitle(); // e.g. "Assessor's Office", "MASO", etc.
                    $rows = $sheet->toArray();

                    foreach ($rows as $index => $row) {
                        if ($index < 2) continue; // skip header

                        $name = trim($row[0] ?? '');
                        if (empty($name)) continue;

                        // Find employee by matching name (Surname, First M.)
                        $employee = $this->findEmployeeByName($name);

                        if (!$employee) {
                            // Sheet name included so HR knows which department's
                            // tab the unmatched name came from.
                            $errors[] = "[{$sheetName}] Employee not found: {$name}";
                            continue;
                        }
                        // Automatically skip JO employees even if they appear in the file
                        // $status = strtolower($employee->employment_type ?? '');
                        // if (in_array($status, ['jo', 'job_order', 'cos', 'job order'])) {
                        //     continue;
                        // }
                        // ----------------------

                        // NEW — JO employees don't accrue VL/SL, and inactive/resigned employees shouldn't accrue anything
                        if ($employee->employment_status === 'job_order' || !$employee->is_active) {
                            $skipped[] = "[{$sheetName}] {$employee->first_name} {$employee->surname}: skipped (Job Order or inactive — not eligible for VL/SL accrual).";
                            continue;
                        }
                        $alreadyExists = Attendance::where('employee_id', $employee->id)
                            ->where('month', $monthName)
                            ->where('year', $year)
                            ->exists();

                        if ($alreadyExists) {
                            $skipped[] = "[{$sheetName}] {$employee->first_name} {$employee->surname}: already has attendance for {$monthName} {$year}, skipped.";
                            continue;
                        }

                        $absentWithLeaveRaw = trim($row[2] ?? '');
                        $absentWithoutLeaveRaw = trim($row[3] ?? '');

                        $absentWithLeaveDays = $this->countDatesInString($absentWithLeaveRaw);
                        $absentWithoutLeaveDays = $this->countDatesInString($absentWithoutLeaveRaw);

                        $lateAm = $this->parseMinutes($row[4] ?? '');
                        $latePm = $this->parseMinutes($row[5] ?? '');
                        $utAm = $this->parseMinutes($row[6] ?? '');
                        $utPm = $this->parseMinutes($row[7] ?? '');

                        $computation = $this->computationService->computeMonthlyCredits([
                            'month'                       => $month,
                            'year'                        => $year,
                            'absent_with_leave_days'      => $absentWithLeaveDays,
                            'absent_without_leave_days'   => $absentWithoutLeaveDays,
                            'late_am_minutes'             => $lateAm,
                            'late_pm_minutes'             => $latePm,
                            'undertime_am_minutes'        => $utAm,
                            'undertime_pm_minutes'        => $utPm,
                        ]);

                        // Save the attendance summary record
                        $attendance = Attendance::create([
                            'employee_id'                 => $employee->id,
                            'month'                       => $monthName,
                            'year'                        => $year,
                            'total_working_days'          => 30,
                            'absent_with_leave_days'      => $absentWithLeaveDays,
                            'absent_without_leave_days'   => $absentWithoutLeaveDays,
                            'late_am_minutes'             => $lateAm,
                            'late_pm_minutes'             => $latePm,
                            'undertime_am_minutes'        => $utAm,
                            'undertime_pm_minutes'        => $utPm,
                            'vl_earned'                   => $computation['vl_earned'],
                            'sl_earned'                   => $computation['sl_earned'],
                            'tardiness_equivalent_days'   => $computation['tardiness_equivalent_days'],
                            'uploaded_by'                 => $request->user()->id,
                        ]);

                        // // Update Leave Credits
                        // $this->updateLeaveCredits($employee, $year, $computation);

                        // Update Leave Credits
                        // $this->updateLeaveCredits($employee, $year, $computation, $request->user()->id);

                        //tp
                        $this->updateLeaveCredits($employee, $month, $year, $computation, $request->user()->id, $attendance);

                        $results[] = [
                            'sheet'              => $sheetName,
                            'employee'           => $employee->first_name . ' ' . $employee->surname,
                            'vl_earned'          => $computation['vl_earned'],
                            'sl_earned'          => $computation['sl_earned'],
                            'tardiness_deducted' => $computation['tardiness_equivalent_days'],
                        ];
                    }
                }
            });
        } catch (\Throwable $e) {
            // Whole batch rolled back — nothing from ANY sheet in this
            // upload was saved, same all-or-nothing guarantee as before,
            // just now spanning the entire multi-sheet workbook.
            return response()->json([
                'message' => 'Attendance processing failed — no changes were saved. ' . $e->getMessage(),
                'results' => [],
                'errors'  => [],
                'skipped' => [],
            ], 500);
        }
        // NEW — log the upload as one summary entry, not per-employee
        $monthName = Carbon::create($year, $month, 1)->format('F');
        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'attendance.uploaded',
            'description'  => "Uploaded attendance for {$monthName} {$year} — " . count($results) . " processed, " . count($errors) . " errors, " . count($skipped) . " skipped",
            'subject_type' => 'Attendance',
            'subject_id'   => null, // no single record — this action affects many
        ]);

        $missingByDepartment = $this->getMissingAttendance($monthName, $year);
        return response()->json([
            'message' => 'Attendance processed successfully',

            'results' => $results,
            'errors'  => $errors,
            'skipped' => $skipped,
            'total_missing'         => $missingByDepartment->flatten(1)->count(),
            'missing_by_department' => $missingByDepartment,
        ]);
    }

    // NEW — standalone reconciliation check, independent of uploading
    public function checkMissingAttendance(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer',
        ]);

        $monthName = Carbon::create($request->year, $request->month, 1)->format('F');
        $missingByDepartment = $this->getMissingAttendance($monthName, $request->year);

        return response()->json([
            'month'                 => $monthName,
            'year'                  => $request->year,
            'total_missing'         => $missingByDepartment->flatten(1)->count(),
            'missing_by_department' => $missingByDepartment,
        ]);
    }

    private function getMissingAttendance(string $monthName, int $year)
    {
        return Employee::where('is_active', true)
            ->whereIn('employment_status', ['permanent', 'casual', 'elected']) // JO excluded — not tracked for VL/SL
            ->whereDoesntHave('attendances', function ($query) use ($monthName, $year) {
                $query->where('month', $monthName)->where('year', $year);
            })
            ->with('department:id,name')
            ->select(['id', 'first_name', 'surname', 'department_id', 'employment_status'])
            ->get()
            ->groupBy(fn($employee) => $employee->department->name ?? 'Unassigned')
            ->map(function ($employees) {
                return $employees->map(fn($e) => [
                    'id'                => $e->id,
                    'name'              => "{$e->first_name} {$e->surname}",
                    'employment_status' => $e->employment_status,
                ]);
            });
    }

    private function findEmployeeByName(string $name): ?Employee
    {
        $parts = explode(',', $name);
        if (count($parts) < 2) return null;

        $surname = trim($parts[0]);
        $firstPart = trim($parts[1]);
        $firstPart = str_replace('.', '', $firstPart);

        $pieces = array_values(array_filter(explode(' ', $firstPart)));
        if (count($pieces) === 0) return null;

        $middleInitial = count($pieces) > 1 ? trim(end($pieces)) : null;
        $firstNamePieces = count($pieces) > 1 ? array_slice($pieces, 0, -1) : $pieces;
        $firstName = implode(' ', $firstNamePieces);

        // Base match: surname + first name only
        $candidates = Employee::whereRaw('LOWER(surname) = ?', [strtolower($surname)])
            ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
            ->get();

        if ($candidates->count() === 0) {
            return null; // no match — surfaces as an error, same as today
        }

        if ($candidates->count() === 1) {
            return $candidates->first(); // unambiguous, safe regardless of middle initial
        }

        // Multiple people share surname + first name — middle initial is now REQUIRED to disambiguate
        if (!$middleInitial) {
            return null; // can't safely pick one — force it into the errors list instead of guessing
        }

        $filtered = $candidates->filter(function ($employee) use ($middleInitial) {
            return strtolower(substr((string) $employee->middle_name, 0, 1)) === strtolower($middleInitial);
        });

        return $filtered->count() === 1 ? $filtered->first() : null;
    }
    private function countDatesInString(string $dateString): int
    {
        if (empty($dateString)) return 0;
        $dates = array_filter(array_map('trim', explode(',', $dateString)));
        return count($dates);
    }
    public function index(Request $request)
    {
        $query = \App\Models\Attendance::with('employee:id,first_name,middle_name,surname,department_id', 'employee.department:id,name');

        if ($request->filled('month')) {
            $monthName = \Carbon\Carbon::create(null, $request->month, 1)->format('F');
            $query->where('month', $monthName);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $summaries = $query->orderBy('created_at', 'desc')->get();

        return response()->json($summaries);
    }
    private function parseMinutes($value): int

    {
        // Format example: "353(11)" -> we only need the minutes (353)
        if (empty($value)) return 0;
        preg_match('/^(\d+)/', trim($value), $matches);
        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    private function updateLeaveCredits(Employee $employee, int $month, int $year, array $computation, int $uploadedBy, $attendance)
    {
        $vlConfig = LeaveConfiguration::where('code', 'VL')->first();
        $slConfig = LeaveConfiguration::where('code', 'SL')->first();

        if ($vlConfig) {
            $vlCredit = LeaveCredit::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_configuration_id' => $vlConfig->id, 'year' => $year],
                ['total_credits' => 0, 'used_credits' => 0, 'remaining_balance' => 0]
            );

            // Earned VL adds normally
            $vlCredit->total_credits += $computation['vl_earned'];
            $vlCredit->last_updated = now();
            $vlCredit->save();

            // Tardiness deducted through deductLeave() so it's capped at whatever balance exists
            $tardinessDays = $computation['tardiness_equivalent_days'];
            if ($tardinessDays > 0) {
                $unmetDays = $vlCredit->deductLeave($tardinessDays);

                // Balance couldn't absorb the full deduction — record as LWOP,
                // same mechanism as leave-application LWOP, so it shows on the
                // leave card's "Abs. Und. WOP" column automatically.
                if ($unmetDays > 0) {
                    $attendance->lwop_days = $unmetDays;
                    $attendance->save();
                    $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
                    $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

                    LeaveRecord::create([
                        'employee_id'            => $employee->id,
                        'leave_configuration_id' => $vlConfig->id,
                        'recorded_by'            => $uploadedBy,
                        'start_date'             => $periodStart,
                        'end_date'               => $periodEnd,
                        'days_taken'             => $unmetDays,
                        'no_pay_days'            => $unmetDays,
                        'remarks'                => "Tardiness/undertime for {$periodStart->format('F Y')} exceeded available VL balance by {$unmetDays} day(s) — recorded as LWOP.",
                    ]);

                    ActivityLog::create([
                        'user_id'      => $uploadedBy,
                        'action'       => 'leave_credit.tardiness_exceeded_balance',
                        'description'  => "{$employee->first_name} {$employee->surname}: {$unmetDays} day(s) of tardiness exceeded available VL balance for {$periodStart->format('F Y')} — recorded as LWOP.",
                        'subject_type' => 'LeaveCredit',
                        'subject_id'   => $vlCredit->id,
                    ]);
                }
            }
        }

        if ($slConfig) {
            $slCredit = LeaveCredit::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_configuration_id' => $slConfig->id, 'year' => $year],
                ['total_credits' => 0, 'used_credits' => 0, 'remaining_balance' => 0]
            );
            $slCredit->total_credits += $computation['sl_earned'];
            $slCredit->last_updated = now();
            $slCredit->save();
        }
    }
}
