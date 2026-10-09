<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveAccrual extends Model
{
    protected $fillable = ['employee_id', 'accrual_month', 'accrual_date', 'vl_earned', 'sl_earned'];

    protected $casts = [
        'accrual_month' => 'date',
        'accrual_date'  => 'date',
        'vl_earned'     => 'decimal:3',
        'sl_earned'     => 'decimal:3',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
