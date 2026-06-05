<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveConfiguration extends Model
{
    protected $fillable = [
        'name',
        'code',
        'application_to',
        'can_carry_over',
        'can_monetize',
        'fixed_days',
        'monthly_credit',
        'credit_type',
        'description'
    ];

    protected $casts = [
        'can_carry_over' => 'boolean',
        'can_monetize' => 'boolean',
        'fixed_days' => 'decimal:2',
        'monthly_credit' => 'decimal:2',
    ];

    // public function leaves(){
    //     return $this->hasMany(LeaveCredit::class);
    // }

    // public function leaveRecords(){
    //     return $this->hasMany(LeaveRecord::class);
    // }
}
