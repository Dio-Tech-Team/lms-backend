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
        'grant_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'application_to' => 'array',
        'can_carry_over' => 'boolean',
        'can_monetize' => 'boolean',
        'fixed_days' => 'decimal:2',
        'monthly_credit' => 'decimal:2',
        'is_active' => 'boolean'
    ];
    protected $attributes = [
        'application_to' => '["all"]',
    ];

    // public function leaves(){
    //     return $this->hasMany(LeaveCredit::class);
    // }

    // public function leaveRecords(){
    //     return $this->hasMany(LeaveRecord::class);
    // }
}
