<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\LeaveConfiguration;
use Illuminate\Database\Eloquent\Model;

class LeaveApplication extends Model
{
    protected $fillable = [

        'employee_id',
        'leave_configuration_id',
        'start_date',
        'end_date',
        'days_applied',
        'reason',
        'status',
        'filed_by',
        'applied_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'cancellation_reason',
        'cancelled_at',
        'original_end_date',
        'original_days_applied',
        'credits_returned',
        'lwop_removed',

    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'applied_at'  => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'original_end_date'     => 'date',
        'original_days_applied' => 'decimal:3',
        'days_applied' => 'decimal:3',
        'credits_returned' => 'decimal:3',
        'lwop_removed'     => 'decimal:3',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveConfiguration()
    {
        return $this->belongsTo(LeaveConfiguration::class, 'leave_configuration_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
}
