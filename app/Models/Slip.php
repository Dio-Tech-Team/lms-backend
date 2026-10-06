<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slip extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'time_out',
        'time_in',
        'minutes',
        'equivalent_days',
        'lwop_days',
        'reason',
        'status',
        'recorded_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'date'            => 'date:Y-m-d',
        'equivalent_days' => 'float',
        'lwop_days'       => 'float',
        'cancelled_at'    => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // The LWOP row created when VL couldn't absorb the full deduction
    public function lwopRecord()
    {
        return $this->hasOne(LeaveRecord::class);
    }
}
