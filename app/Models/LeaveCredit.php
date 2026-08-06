<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\LeaveConfiguration;

class LeaveCredit extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_configuration_id',
        'total_credits',
        'used_credits',
        'remaining_balance',
        'year',
        'last_updated'
    ];

    protected $casts = [
        'total_credits' => 'decimal:3',
        'used_credits' => 'decimal:3',
        'remaining_balance' => 'decimal:3',
        'last_updated' => 'datetime',
    ];
    public $timestamps = true;

    protected static function booted(): void
    {
        static::saving(function (LeaveCredit $credit) {
            $credit->remaining_balance = $credit->total_credits - $credit->used_credits;
        });
    }
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveConfiguration()
    {
        return $this->belongsTo(LeaveConfiguration::class);
    }
}
