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
        'total_credits' => 'decimal:2',
        'used_credits' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'last_updated' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveConfiguration()
    {
        return $this->belongsTo(LeaveConfiguration::class);
    }
}
