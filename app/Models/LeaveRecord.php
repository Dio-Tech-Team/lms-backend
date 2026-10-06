<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use App\Models\LeaveCredit;
use Carbon\Carbon;

class LeaveRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_configuration_id',
        'attendance_id',
        'slip_id',
        'leave_application_id',
        'recorded_by',
        'start_date',
        'end_date',
        'days_taken',
        'no_pay_days',
        'remarks',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'days_taken' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        // FL approved for a year AFTER next year was initialized: init
        // already deducted those days as "unused FL" from next year's VL.
        // Now they're used, so give them back — otherwise the employee is
        // charged twice (once as unused, once as taken).
        static::created(function (LeaveRecord $record) {
            $flConfig = LeaveConfiguration::where('code', 'FL')->first();
            if (!$flConfig || (int) $record->leave_configuration_id !== (int) $flConfig->id) return;

            $year = Carbon::parse($record->start_date)->year;

            // Init only applies the FL deduction to employees hired before
            // Jan 1 of that year — mirror that rule
            $employee = Employee::find($record->employee_id);
            if (!$employee || Carbon::parse($employee->date_hired)->gt(Carbon::create($year, 1, 1))) return;

            $vlConfig = LeaveConfiguration::where('code', 'VL')->first();
            if (!$vlConfig) return;

            $nextVl = LeaveCredit::where('employee_id', $record->employee_id)
                ->where('leave_configuration_id', $vlConfig->id)
                ->where('year', $year + 1)
                ->first();

            if (!$nextVl) return;

            $nextVl->total_credits = (float) $nextVl->total_credits + (float) $record->days_taken;
            $nextVl->save();
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveConfiguration()
    {
        return $this->belongsTo(LeaveConfiguration::class, 'leave_configuration_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
