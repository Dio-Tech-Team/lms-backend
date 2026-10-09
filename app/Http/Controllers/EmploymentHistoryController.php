<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmploymentHistory;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\LeaveCreditController;
use Carbon\Carbon;

class EmploymentHistoryController extends Controller
{
    public function index($employeeId)
    {
        $employee = Employee::select(['id', 'first_name', 'surname'])
            ->findOrFail($employeeId);

        $promotion = EmploymentHistory::select('employee_id', 'previous_position', 'new_position', 'previous_employment_status', 'new_employment_status', 'effective_date', 'remarks')
            ->where('employee_id', $employeeId)
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'employee'  => $employee->first_name . ' ' . $employee->surname,
            'promotion' => $promotion,
        ]);
    }

    /**
     * The employee's current position and status always mirror their latest
     * history record (by effective date). Returns true if anything changed.
     */
    private function syncCurrentFromLatest(Employee $employee): bool
    {
        $latest = EmploymentHistory::where('employee_id', $employee->id)
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) return false;

        $changed = $employee->position !== $latest->new_position
            || $employee->employment_status !== $latest->new_employment_status;

        if ($changed) {
            $employee->update([
                'position'          => $latest->new_position,
                'employment_status' => $latest->new_employment_status,
            ]);
        }

        return $changed;
    }

    /**
     * A resignation/retirement that would become the latest record must go
     * through the Resign/Retire buttons, which also deactivate the employee.
     * Here they're allowed only as past history.
     */
    private function blocksEndingAsLatest(Employee $employee, string $status, string $date, ?int $ignoreId = null): bool
    {
        if (!in_array($status, ['resigned', 'retired'], true)) return false;

        // Already resigned/retired — editing that record is fine
        if ($employee->employment_status === $status) return false;

        $latestDate = EmploymentHistory::where('employee_id', $employee->id)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->max('effective_date');

        return !$latestDate || Carbon::parse($date)->gte(Carbon::parse($latestDate));
    }

    /**
     * Adds a history record — either a new change (becomes the latest and
     * updates the profile) or a past record from the paper file (doesn't).
     */
    public function store(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'previous_position'          => 'nullable|string|max:255',
            'new_position'               => 'required|string|max:255|exists:positions,title',
            'previous_employment_status' => 'nullable|in:permanent,casual,elected,job_order,resigned,retired',
            'new_employment_status'      => 'required|in:permanent,casual,elected,job_order,resigned,retired',
            'effective_date'             => 'required|date|before_or_equal:today',
            'remarks'                    => 'nullable|string',
        ]);

        if ($this->blocksEndingAsLatest($employee, $request->new_employment_status, $request->effective_date)) {
            return response()->json([
                'message' => 'A resignation dated on or after the latest record must be done with the Resign button.',
            ], 422);
        }

        [$history, $synced] = DB::transaction(function () use ($request, $employee) {
            $history = EmploymentHistory::create([
                'employee_id'                => $employee->id,
                'previous_position'          => $request->previous_position,
                'new_position'               => $request->new_position,
                'previous_employment_status' => $request->previous_employment_status,
                'new_employment_status'      => $request->new_employment_status,
                'effective_date'             => $request->effective_date,
                'remarks'                    => $request->remarks,
            ]);

            $synced = $this->syncCurrentFromLatest($employee);

            // A status change can make new leave types apply (e.g. JO → permanent)
            if ($synced) {
                (new LeaveCreditController())->initializeSingleEmployeeCredits($employee->id);
            }

            ActivityLog::create([
                'user_id'      => request()->user()->id,
                'action'       => 'employment_history.recorded',
                'description'  => ($synced
                    ? "Recorded position change for {$employee->first_name} {$employee->surname}: {$request->new_position}"
                    : "Added past employment record for {$employee->first_name} {$employee->surname}: {$request->new_position} ({$request->effective_date})"),
                'subject_type' => 'Employee',
                'subject_id'   => $employee->id,
            ]);

            return [$history, $synced];
        });

        return response()->json([
            'message'               => 'Employment history recorded successfully',
            'synced_current_record' => $synced,
            'history'               => $history->only([
                'id',
                'employee_id',
                'previous_position',
                'new_position',
                'previous_employment_status',
                'new_employment_status',
                'effective_date',
                'remarks',
            ]),
        ], 201);
    }

    public function update(Request $request, $employeeId, $promotionId)
    {
        $history = EmploymentHistory::where('employee_id', $employeeId)
            ->where('id', $promotionId)
            ->firstOrFail();

        $request->validate([
            'previous_position'          => 'nullable|string|max:255',
            'new_position'               => 'required|string|max:255|exists:positions,title',
            'previous_employment_status' => 'nullable|in:permanent,casual,elected,job_order,resigned,retired',
            'new_employment_status'      => 'required|in:permanent,casual,elected,job_order,resigned,retired',
            'effective_date'             => 'required|date|before_or_equal:today',
        ]);

        $employee = Employee::findOrFail($employeeId);

        if ($this->blocksEndingAsLatest($employee, $request->new_employment_status, $request->effective_date, $history->id)) {
            return response()->json([
                'message' => 'A resignation dated on or after the latest record must be done with the Resign button.',
            ], 422);
        }

        $synced = DB::transaction(function () use ($request, $history, $employee) {
            $history->update($request->only([
                'previous_position',
                'new_position',
                'previous_employment_status',
                'new_employment_status',
                'effective_date',
            ]));

            // Re-check after the edit — a changed date can change which record is latest
            $synced = $this->syncCurrentFromLatest($employee);

            ActivityLog::create([
                'user_id'      => request()->user()->id,
                'action'       => 'employment_history.updated',
                'description'  => "Corrected employment history record #{$history->id} for {$employee->first_name} {$employee->surname}"
                    . ($synced ? ' (also updated current position/status)' : ''),
                'subject_type' => 'Employee',
                'subject_id'   => $employee->id,
            ]);

            return $synced;
        });

        return response()->json([
            'message'               => 'Employment history updated successfully',
            'history'               => $history,
            'synced_current_record' => $synced,
        ]);
    }

    public function destroy($employeeId, $promotionId)
    {
        $employee = Employee::findOrFail($employeeId);

        $affected = DB::transaction(function () use ($employee, $promotionId) {
            $affected = EmploymentHistory::where('employee_id', $employee->id)
                ->where('id', $promotionId)
                ->delete();

            if ($affected) {
                // Deleting the latest record falls back to the one before it
                $this->syncCurrentFromLatest($employee);
            }

            return $affected;
        });

        if (!$affected) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        ActivityLog::create([
            'user_id'      => request()->user()->id,
            'action'       => 'employment_history.deleted',
            'description'  => "Deleted employment history record #{$promotionId} for {$employee->first_name} {$employee->surname}",
            'subject_type' => 'Employee',
            'subject_id'   => $employee->id,
        ]);

        return response()->json(['message' => 'Employment history deleted successfully']);
    }
}
