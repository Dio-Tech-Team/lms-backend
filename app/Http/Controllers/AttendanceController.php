<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LeaveCredit;
use App\Models\LeaveConfiguration;
use App\Service\LeaveCreditComputationService;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        // return response()->json([
        //     'all' => $request->all(),
        //     'files' => $request->files->all(),
        //     'allFiles' => $request->allFiles(),
        //     'hasFile' => $request->hasFile('file'),
        //     'file_instance' => $request->file('file'),
        // ]);
        $request->validate([
            'file'       => 'required|file|mimes:xlsx,xls,csv',
            'month'      => 'required|integer|min:1|max:12',
            'year'       => 'required|integer',
            'department' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $month = $request->month;
        $year = $request->year;

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Assume row 0 is the header row, data starts at row 1
        $results = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if ($index < 2) continue; // skip header

            $name = trim($row[0] ?? '');
            if (empty($name)) continue;

            // Find employee by matching name (Surname, First M.)
            $employee = $this->findEmployeeByName($name);

            if (!$employee) {
                $errors[] = "Employee not found: {$name}";
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
            $summary = Attendance::create([
                'employee_id'                 => $employee->id,
                'month'                       => Carbon::create($year, $month, 1)->format('F'),
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
                'employee' => $employee->first_name . ' ' . $employee->surname,
                'vl_earned' => $computation['vl_earned'],
                'sl_earned' => $computation['sl_earned'],
                'tardiness_deducted' => $computation['tardiness_equivalent_days'],
            ];
        }

        return response()->json([
            'message' => 'Attendance processed successfully',
            'results' => $results,
            'errors'  => $errors,
        ]);
        // return response()->json([
        //     'all' => $request->all(),
        //     'hasFile' => $request->hasFile('file'),
        //     'file' => $request->file('file'),
        //     'content_type' => $request->header('Content-Type'),
        // ]);

    }

    // private function findEmployeeByName(string $name)
    // {
    //     // Expected format: "SURNAME, FIRSTNAME M."
    //     $parts = explode(',', $name);
    //     if (count($parts) < 2) return null;

    //     $surname = trim($parts[0]);
    //     $firstNamePart = trim($parts[1]);

    //     return Employee::whereRaw('LOWER(surname) = ?', [strtolower($surname)])
    //         ->whereRaw('LOWER(first_name) LIKE ?', [strtolower(substr($firstNamePart, 0, 3)) . '%'])
    //         ->first();
    // }

    // private function findEmployeeByName(string $name)
    // {
    //     $parts = explode(',', $name);
    //     if (count($parts) < 2) return null;

    //     $surname = trim($parts[0]);
    //     $firstPart = trim($parts[1]);

    //     // remove dot (C.)
    //     $firstPart = str_replace('.', '', $firstPart);

    //     $pieces = explode(' ', $firstPart);

    //     $firstName = trim($pieces[0] ?? '');
    //     $middleInitial = trim($pieces[1] ?? '');

    //     return Employee::whereRaw('LOWER(surname) = ?', [strtolower($surname)])
    //         ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
    //         ->when($middleInitial, function ($q) use ($middleInitial) {
    //             $q->whereRaw('LEFT(LOWER(middle_name), 1) = ?', [strtolower($middleInitial)]);
    //         })
    //         ->first();
    // }
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
