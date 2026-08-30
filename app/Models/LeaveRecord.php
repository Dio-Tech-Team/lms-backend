<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\LeaveConfiguration;

class LeaveRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_configuration_id',
        'attendance_id',
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
