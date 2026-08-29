<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use App\Models\LeaveCredit;
use App\Models\LeaveRecord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

/**
 * Reports are read-only views over data other controllers own. Each method
 * returns the same rows either as JSON (for the on-screen preview) or as an
 * .xlsx stream, so the sheet can never disagree with what HR just looked at.
 */
class ReportController extends Controller
{
    private const HEADER_FILL = 'FF1B3B63';   // AppColors.navy
    private const HEADER_FONT = 'FFFFFFFF';

    // -----------------------------------------------------------------
    // 1. Leave Balances Roster
    // -----------------------------------------------------------------

    /**
     * Every active employee with their remaining balance per annual leave
     * type, as of a given year. The report HR reaches for most often.
     */

        // -----------------------------------------------------------------
    // 3. Employee Masterlist
    // -----------------------------------------------------------------

    /**
     * The plain roster. The Employees page already shows this on screen —
     * what this adds is a spreadsheet HR can hand to someone else.
     */
    public function employeeMasterlist(Request $request)
    {
        $validated = $request->validate([
            'department_id'     => 'nullable|exists:departments,id',
            'employment_status' => 'nullable|in:permanent,casual,elected,job_order,resigned,retired',
            'format'            => 'nullable|in:json,xlsx',
        ]);

        $employees = Employee::select([
            'employees.id',
            'employees.first_name',
            'employees.middle_name',
            'employees.surname',
            'employees.id_number',
            'employees.sex',
            'employees.position',
            'employees.employment_status',
            'employees.date_hired',
            'employees.contact_number',
            'departments.name as department_name',
            'users.email',
        ])
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->where('employees.is_active', true)
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->where('employees.department_id', $request->department_id);
            })
            ->when($request->filled('employment_status'), function ($q) use ($request) {
                $q->where('employees.employment_status', $request->employment_status);
            })
            ->orderBy('departments.name')
            ->orderBy('employees.surname')
            ->orderBy('employees.first_name')
            ->get();

        $rows = $employees->map(fn($e) => [
            'name'              => trim("{$e->surname}, {$e->first_name} " . ($e->middle_name ?? '')),
            'id_number'         => $e->id_number,
            'sex'               => ucfirst($e->sex ?? ''),
            'department'        => $e->department_name,
            'position'          => $e->position,
            'employment_status' => ucfirst(str_replace('_', ' ', $e->employment_status)),
            'date_hired'        => $e->date_hired ? Carbon::parse($e->date_hired)->format('Y-m-d') : '',
            'contact_number'    => $e->contact_number ?? '',
            'email'             => $e->email ?? '',
        ])->values();

        if (($validated['format'] ?? 'json') === 'json') {
            return response()->json([
                'report'       => 'Employee Masterlist',
                'generated_at' => now()->toDateTimeString(),
                'rows'         => $rows,
            ]);
        }

        return $this->streamXlsx(
            title: 'Employee Masterlist',
            subtitle: $rows->count() . ' active employee(s)',
            headers: [
                'Employee',
                'ID Number',
                'Sex',
                'Department',
                'Position',
                'Status',
                'Date Hired',
                'Contact',
                'Email',
            ],
            rows: $rows->map(fn($r) => array_values($r))->toArray(),
            filename: 'employee-masterlist-' . now()->format('Y-m-d') . '.xlsx',
        );
    }
    public function leaveBalances(Request $request)
    {
        $validated = $request->validate([
            'year'          => 'nullable|integer|min:2020|max:2099',
            'department_id' => 'nullable|exists:departments,id',
            'format'        => 'nullable|in:json,xlsx',
        ]);

        $year = $validated['year'] ?? now()->year;

        // Only the auto-initialized types get a column. Event-granted leaves
        // (Maternity, Study, etc.) exist for a handful of employees at a
        // time, so a column each would be almost entirely empty.
        $configs = LeaveConfiguration::where('is_active', true)
            ->where('grant_type', 'annual_auto')
            ->orderBy('id')
            ->get(['id', 'name', 'code']);

        $employees = Employee::select([
            'employees.id',
            'employees.first_name',
            'employees.middle_name',
            'employees.surname',
            'employees.id_number',
            'employees.position',
            'employees.employment_status',
            'departments.name as department_name',
        ])
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('employees.is_active', true)
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->where('employees.department_id', $request->department_id);
            })
            ->orderBy('departments.name')
            ->orderBy('employees.surname')
            ->orderBy('employees.first_name')
            ->get();

        // One query for every balance, keyed in memory — the alternative is
        // a query per employee per leave type.
        $creditMap = LeaveCredit::where('year', $year)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get(['employee_id', 'leave_configuration_id', 'remaining_balance'])
            ->groupBy('employee_id')
            ->map(fn($rows) => $rows->keyBy('leave_configuration_id')
                ->map(fn($r) => (float) $r->remaining_balance)
                ->toArray())
            ->toArray();

        $rows = $employees->map(function ($e) use ($configs, $creditMap) {
            $balances = [];
            foreach ($configs as $config) {
                $balances[$config->code] = $creditMap[$e->id][$config->id] ?? 0.0;
            }

            return [
                'employee_id'       => $e->id,
                'name'              => trim("{$e->surname}, {$e->first_name} " . ($e->middle_name ?? '')),
                'id_number'         => $e->id_number,
                'department'        => $e->department_name,
                'position'          => $e->position,
                'employment_status' => ucfirst(str_replace('_', ' ', $e->employment_status)),
                'balances'          => $balances,
            ];
        })->values();

        if (($validated['format'] ?? 'json') === 'json') {
            return response()->json([
                'report'      => 'Leave Balances Roster',
                'year'        => $year,
                'generated_at' => now()->toDateTimeString(),
                'leave_types' => $configs->map(fn($c) => ['code' => $c->code, 'name' => $c->name]),
                'rows'        => $rows,
            ]);
        }

        $headers = ['Employee', 'ID Number', 'Department', 'Position', 'Status'];
        foreach ($configs as $config) {
            $headers[] = $config->code;
        }

        $data = $rows->map(function ($r) use ($configs) {
            $line = [
                $r['name'],
                $r['id_number'],
                $r['department'],
                $r['position'],
                $r['employment_status'],
            ];
            foreach ($configs as $config) {
                $line[] = $r['balances'][$config->code];
            }
            return $line;
        })->toArray();

        return $this->streamXlsx(
            title: 'Leave Balances',
            subtitle: "As of {$year} (" . now()->format('F j') . ') · '
                . $rows->count() . ' employee(s)',
            headers: $headers,
            rows: $data,
            filename: "leave-balances-{$year}.xlsx",
        );
    }

    // -----------------------------------------------------------------
    // 2. Monthly Leave Utilization
    // -----------------------------------------------------------------

    /**
     * Who took leave in a given month, of what type, for how many days.
     * Answers "how many employees were on leave this month" and feeds the
     * department-level view of it.
     */
    public function leaveUtilization(Request $request)
    {
        $validated = $request->validate([
            'year'          => 'nullable|integer|min:2020|max:2099',
            'month'         => 'nullable|integer|min:1|max:12',
            'leave_configuration_id' => 'nullable|exists:leave_configurations,id',
            'department_id' => 'nullable|exists:departments,id',
            'format'        => 'nullable|in:json,xlsx',
        ]);

        $year  = $validated['year'] ?? now()->year;
        $month = $validated['month'] ?? null;

        $records = LeaveRecord::select([
            'leave_records.id',
            'leave_records.start_date',
            'leave_records.end_date',
            'leave_records.days_taken',
            'leave_records.no_pay_days',
            'employees.first_name',
            'employees.middle_name',
            'employees.surname',
            'employees.id_number',
            'departments.name as department_name',
            'leave_configurations.name as leave_type_name',
            'leave_configurations.code as leave_type_code',
        ])
            ->join('employees', 'leave_records.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->join('leave_configurations', 'leave_records.leave_configuration_id', '=', 'leave_configurations.id')
            ->whereYear('leave_records.start_date', $year)
            ->when($month, function ($q) use ($month) {
                $q->whereMonth('leave_records.start_date', $month);
            })
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->where('employees.department_id', $request->department_id);
            })
            ->when($request->filled('leave_configuration_id'), function ($q) use ($request) {
                $q->where('leave_records.leave_configuration_id', $request->leave_configuration_id);
            })
            ->orderBy('leave_records.start_date')
            ->orderBy('employees.surname')
            ->get();

        $rows = $records->map(fn($r) => [
            'name'          => trim("{$r->surname}, {$r->first_name} " . ($r->middle_name ?? '')),
            'id_number'     => $r->id_number,
            'department'    => $r->department_name,
            'leave_type'    => $r->leave_type_name,
            'code'          => $r->leave_type_code,
            'start_date'    => Carbon::parse($r->start_date)->format('Y-m-d'),
            'end_date'      => Carbon::parse($r->end_date)->format('Y-m-d'),
            'days_taken'    => (float) $r->days_taken,
            'no_pay_days'   => (float) $r->no_pay_days,
        ])->values();

        // The headline figures HR actually quotes: how many people, how many
        // days, and how much of it was unpaid.
        $summary = [
            'employees_on_leave' => $records->pluck('id_number')->unique()->count(),
            'total_records'      => $records->count(),
            'total_days'         => round($records->sum('days_taken'), 3),
            'total_no_pay_days'  => round($records->sum('no_pay_days'), 3),
            'by_type'            => $records->groupBy('leave_type_name')
                ->map(fn($g) => [
                    'records' => $g->count(),
                    'days'    => round($g->sum('days_taken'), 3),
                ]),
            'by_department'      => $records->groupBy('department_name')
                ->map(fn($g) => [
                    'employees' => $g->pluck('id_number')->unique()->count(),
                    'days'      => round($g->sum('days_taken'), 3),
                ]),
        ];

        $periodLabel = $month
            ? Carbon::create($year, $month, 1)->format('F Y')
            : "Year $year";

        if (($validated['format'] ?? 'json') === 'json') {
            return response()->json([
                'report'       => 'Leave Utilization',
                'period'       => $periodLabel,
                'generated_at' => now()->toDateTimeString(),
                'summary'      => $summary,
                'rows'         => $rows,
            ]);
        }

        return $this->streamXlsx(
            title: 'Leave Utilization Report',
            subtitle: "$periodLabel · {$summary['employees_on_leave']} employee(s) · "
                . "{$summary['total_days']} day(s) taken",
            headers: [
                'Employee',
                'ID Number',
                'Department',
                'Leave Type',
                'Start',
                'End',
                'Days',
                'Without Pay',
            ],
            rows: $rows->map(fn($r) => [
                $r['name'],
                $r['id_number'],
                $r['department'],
                $r['leave_type'],
                $r['start_date'],
                $r['end_date'],
                $r['days_taken'],
                $r['no_pay_days'],
            ])->toArray(),
            filename: 'leave-utilization-' . ($month ? "$year-$month" : $year) . '.xlsx',
        );
    }

    // -----------------------------------------------------------------
    // Shared writer
    // -----------------------------------------------------------------

    /**
     * Every report gets the same sheet: agency heading, report title, the
     * period it covers, then a bordered table. Kept in one place so a
     * fifth report is a method, not another copy of this formatting.
     */
    /**
     * One left-aligned heading line, then the table. The agency name and a
     * generated-at stamp were noise — whoever opens this knows both.
     */
    private function streamXlsx(
        string $title,
        string $subtitle,
        array $headers,
        array $rows,
        string $filename,
    ) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $lastCol = $this->columnLetter(count($headers));

        $sheet->setCellValue('A1', "{$title} — {$subtitle}");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        // Header row
        $headerRow = 3;
        foreach ($headers as $i => $header) {
            $sheet->setCellValue($this->columnLetter($i + 1) . $headerRow, $header);
        }

        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)
            ->getColor()->setARGB(self::HEADER_FONT);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Data
        $rowNum = $headerRow + 1;
        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $value) {
                $sheet->setCellValue($this->columnLetter($i + 1) . $rowNum, $value);
            }
            $rowNum++;
        }

        $lastRow = $rowNum - 1;

        if ($lastRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                ->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFBBBBBB');
        }

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }

        // Header stays visible when HR scrolls a 400-row roster.
        $sheet->freezePane('A' . ($headerRow + 1));

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** 1 => A, 27 => AA. */
    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }
}
