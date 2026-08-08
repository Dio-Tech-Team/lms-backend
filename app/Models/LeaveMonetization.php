<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveMonetization extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_configuration_id',
        'days_monetized',
        'reason',
        'status',
        'filed_by',
        'applied_at',
        'reviewed_by',
        'reviewed_at',
        'remarks',
    ];

    protected $casts = [
        'days_monetized' => 'decimal:3',
        'applied_at'      => 'datetime',
        'reviewed_at'     => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveConfiguration()
    {
        return $this->belongsTo(LeaveConfiguration::class);
    }

    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
