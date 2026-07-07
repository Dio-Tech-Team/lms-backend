<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRecord;
use App\Models\LeaveCredit;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class LeaveRecordController extends Controller
{
    public function index(Request $request)
    {
        // OPTIMIZED: INNER JOIN + vertical partitioning + pagination
        // Fixed: last_name → surname bug
        $records = LeaveRecord::select([
            'leave_records.id',
            'leave_records.employee_id',
            'leave_records.start_date',
            'leave_records.end_date',
            'leave_records.days_taken',
            'leave_records.remarks',
            'leave_records.created_at',
            'employees.first_name',
            'employees.surname',
            'leave_configurations.name as leave_type',
            'leave_configurations.code',
            'users.username as recorded_by',
        ])
            ->join('employees', 'leave_records.employee_id', '=', 'employees.id')
            ->join('leave_configurations', 'leave_records.leave_configuration_id', '=', 'leave_configurations.id')
            ->join('users', 'leave_records.recorded_by', '=', 'users.id')
            ->when($request->employee_id, function ($query) use ($request) {
                $query->where('leave_records.employee_id', $request->employee_id);
            })
            ->orderBy('leave_records.created_at', 'desc')
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('employees.first_name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('employees.surname', 'LIKE', '%' . $request->search . '%');
                });
            })
            // ->when($request->year, function ($query) use ($request) {
            //     $query->whereYear('leave_records.created_at', $request->year);
            // })->when($request->leave_type, function ($query) use ($request) {
            //     $query->where('leave_configurations.code', $request->leave_type);
            // })
            ->when($request->filled('year'), function ($query) use ($request) {
                $query->whereYear('leave_records.start_date', $request->year);
            })
            ->when($request->filled('leave_type'), function ($query) use ($request) {
                $query->where('leave_configurations.code', $request->leave_type);
            })
            ->when($request->department_id, function ($query) use ($request) {
                $query->where('employees.department_id', $request->department_id);
            })
            ->paginate(15);


        return response()->json($records);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'leave_configuration_id' => 'required|exists:leave_configurations,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'days_taken'      => 'required|numeric|min:0.5',
            'remarks'         => 'nullable|string',
        ]);

        $validated['recorded_by'] = $request->user()->id;

        // OPTIMIZED: wrap both writes in transaction
        $record = DB::transaction(function () use ($validated) {
            $record = LeaveRecord::create($validated);

            // Update credit in same transaction
            LeaveCredit::where('employee_id', $validated['employee_id'])
                ->where('leave_configuration_id', $validated['leave_configuration_id'])
                ->where('year', now()->year)
                ->update([
                    'used_credits'      => DB::raw('used_credits + ' . $validated['days_taken']),
                    'remaining_balance' => DB::raw('remaining_balance - ' . $validated['days_taken']),
                    'last_updated'      => now(),
                ]);

            return $record;
        });

        return response()->json([
            'message' => 'Leave record created successfully',
            'record'  => $record->only([
                'id',
                'employee_id',
                'leave_configuration_id',
                'start_date',
                'end_date',
                'days_taken',
                'remarks'
            ]),
        ], 201);
    }

    public function show(string $id)
    {
        // OPTIMIZED: INNER JOIN + vertical partitioning
        $record = LeaveRecord::select([
            'leave_records.id',
            'leave_records.employee_id',
            'leave_records.start_date',
            'leave_records.end_date',
            'leave_records.days_taken',
            'leave_records.remarks',
            'leave_records.created_at',
            'employees.first_name',
            'employees.surname',
            'leave_configurations.name as leave_type',
            'leave_configurations.code',
            'users.username as recorded_by',
        ])
            ->join('employees', 'leave_records.employee_id', '=', 'employees.id')
            ->join('leave_configurations', 'leave_records.leave_configuration_id', '=', 'leave_configurations.id')
            ->join('users', 'leave_records.recorded_by', '=', 'users.id')
            ->findOrFail($id);

        return response()->json($record);
    }


    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date',
            'days_taken' => 'sometimes|numeric|min:0.5',
            'remarks'    => 'nullable|string',
        ]);

        // OPTIMIZED: single findOrFail + update
        $record = LeaveRecord::findOrFail($id);
        $record->update($validated);

        return response()->json([
            'message' => 'Leave record updated successfully',
            'record'  => $record->only([
                'id',
                'start_date',
                'end_date',
                'days_taken',
                'remarks'
            ]),
        ]);
    }
    // public function summary()
    // {
    //     $summary = Employee::select([
    //         'employees.id',
    //         'employees.first_name',
    //         'employees.surname',
    //         'employees.position',
    //         'departments.name as department_name',
    //     ])
    //         ->join('departments', 'employees.department_id', '=', 'departments.id')
    //         ->withSum(['leaveRecords as vl_used' => function ($query) {
    //             $query->join('leave_configurations', 'leave_records.leave_configuration_id', '=', 'leave_configurations.id')
    //                 ->where('leave_configurations.code', 'VL')
    //                 ->whereYear('leave_records.created_at', now()->year);
    //         }], 'days_taken')
    //         ->withSum(['leaveRecords as sl_used' => function ($query) {
    //             $query->join('leave_configurations', 'leave_records.leave_configuration_id', '=', 'leave_configurations.id')
    //                 ->where('leave_configurations.code', 'SL')
    //                 ->whereYear('leave_records.created_at', now()->year);
    //         }], 'days_taken')
    //         ->where('employees.is_active', true)
    //         ->paginate(10);

    //     return response()->json($summary);
    // }
    public function summary(Request $request)
    {
        $year = $request->year ?? now()->year;

        $summary = Employee::select([
            'employees.id',
            'employees.first_name',
            'employees.surname',
            'employees.position',
            'departments.name as department_name',
        ])
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->withSum(['leaveRecords as vl_used' => function ($query) use ($year) {
                $query->join('leave_configurations', 'leave_records.leave_configuration_id', '=', 'leave_configurations.id')
                    ->where('leave_configurations.code', 'VL')
                    ->whereYear('leave_records.created_at', $year);
            }], 'days_taken')
            ->withSum(['leaveRecords as sl_used' => function ($query) use ($year) {
                $query->join('leave_configurations', 'leave_records.leave_configuration_id', '=', 'leave_configurations.id')
                    ->where('leave_configurations.code', 'SL')
                    ->whereYear('leave_records.created_at', $year);
            }], 'days_taken')
            ->where('employees.is_active', true)
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('employees.first_name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('employees.surname', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->when($request->department_id, function ($query) use ($request) {  // ← add this
                $query->where('employees.department_id', $request->department_id);
            })
            ->paginate(10);

        return response()->json($summary);
    }

    public function destroy(string $id)
    {
        $record = LeaveRecord::select([
            'id',
            'employee_id',
            'leave_configuration_id',
            'days_taken'
        ])->findOrFail($id);

        // OPTIMIZED: wrap both writes in transaction
        // FIXED: wrong column name leave_config_id → leave_configuration_id
        DB::transaction(function () use ($record) {
            LeaveCredit::where('employee_id', $record->employee_id)
                ->where('leave_configuration_id', $record->leave_configuration_id) // Fixed!
                ->where('year', now()->year)
                ->update([
                    'used_credits'      => DB::raw('used_credits - ' . $record->days_taken),
                    'remaining_balance' => DB::raw('remaining_balance + ' . $record->days_taken),
                    'last_updated'      => now(),
                ]);

            $record->delete();
        });

        return response()->json(['message' => 'Leave record deleted successfully']);
    }
}
