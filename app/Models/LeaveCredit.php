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
        'opening_balance',
        'year',
        'last_updated'
    ];

    protected $casts = [
        'total_credits' => 'decimal:3',
        'used_credits' => 'decimal:3',
        'remaining_balance' => 'decimal:3',
        'opening_balance' => 'decimal:3',
        'last_updated' => 'datetime',
    ];
    public $timestamps = true;

    // protected static function booted(): void
    // {
    //     static::saving(function (LeaveCredit $credit) {
    //         $credit->remaining_balance = $credit->total_credits - $credit->used_credits;
    //     });
    // }
    protected static function booted(): void
    {
        static::saving(function (LeaveCredit $credit) {
            $credit->remaining_balance = $credit->total_credits - $credit->used_credits;
        });

        // Carry-over sync: next year's VL/SL started from this year's
        // remaining balance. If this year changes after next year was
        // initialized (late December upload, late approval, reversal),
        // push the same difference forward so the carry-over stays right.
        static::saved(function (LeaveCredit $credit) {
            $old   = (float) ($credit->getOriginal('remaining_balance') ?? 0);
            $delta = (float) $credit->remaining_balance - $old;

            if (abs($delta) < 0.0005) return;

            $config = $credit->leaveConfiguration;
            if (!$config || !$config->can_carry_over) return;

            $next = LeaveCredit::where('employee_id', $credit->employee_id)
                ->where('leave_configuration_id', $credit->leave_configuration_id)
                ->where('year', $credit->year + 1)
                ->first();

            if (!$next) return; // next year not initialized yet — nothing to sync

            $next->total_credits = (float) $next->total_credits + $delta;
            $next->save(); // fires this hook again, so it chains to later years too
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
    public function deductLeave(float $days): float
    {
        $available = (float) $this->remaining_balance;
        $covered   = min($available, $days);
        $noPay     = $days - $covered;

        $this->used_credits += $covered;
        $this->last_updated  = now();
        $this->save();

        return $noPay;
    }
}
