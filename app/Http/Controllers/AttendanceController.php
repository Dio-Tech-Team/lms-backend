<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LeaveCredit;
use App\Models\LeaveConfiguration;
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
                        Attendance::create([
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

                        // Update Leave Credits
                        $this->updateLeaveCredits($employee, $year, $computation);

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
        return response()->json([
            'message' => 'Attendance processed successfully',
            'results' => $results,
            'errors'  => $errors,
            'skipped' => $skipped,
        ]);
    }
    private function findEmployeeByName(string $name)
    {
        $parts = explode(',', $name);
        if (count($parts) < 2) return null;

        $surname = trim($parts[0]);
        $firstPart = trim($parts[1]);

        // remove dot (C.)
        $firstPart = str_replace('.', '', $firstPart);

        $pieces = array_filter(explode(' ', $firstPart)); // remove empty entries
        $pieces = array_values($pieces); // reindex

        if (count($pieces) === 0) return null;

        // Last piece = middle initial, everything before = first name
        $middleInitial = trim(end($pieces));
        $firstNamePieces = array_slice($pieces, 0, -1);
        $firstName = implode(' ', $firstNamePieces);

        return Employee::whereRaw('LOWER(surname) = ?', [strtolower($surname)])
            ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
            ->when($middleInitial, function ($q) use ($middleInitial) {
                $q->whereRaw('LEFT(LOWER(middle_name), 1) = ?', [strtolower($middleInitial)]);
            })
            ->first();
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


    private function updateLeaveCredits(Employee $employee, int $year, array $computation)
    {

        $vlConfig = LeaveConfiguration::where('code', 'VL')->first();
        $slConfig = LeaveConfiguration::where('code', 'SL')->first();

        if ($vlConfig) {
            $vlCredit = LeaveCredit::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_configuration_id' => $vlConfig->id, 'year' => $year],
                ['total_credits' => 0, 'used_credits' => 0, 'remaining_balance' => 0]
            );
            $netVl = $computation['vl_earned'] - $computation['tardiness_equivalent_days'];
            $vlCredit->total_credits += $netVl;
            $vlCredit->remaining_balance += $netVl;
            $vlCredit->last_updated = now();
            $vlCredit->save();
        }

        if ($slConfig) {
            $slCredit = LeaveCredit::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_configuration_id' => $slConfig->id, 'year' => $year],
                ['total_credits' => 0, 'used_credits' => 0, 'remaining_balance' => 0]
            );
            $slCredit->total_credits += $computation['sl_earned'];
            $slCredit->remaining_balance += $computation['sl_earned'];
            $slCredit->last_updated = now();
            $slCredit->save();
        }
    }
}
