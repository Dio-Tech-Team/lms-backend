<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [

        'employee_id',
        'month',
        'year',
        'total_working_days',
        'absent_with_leave_days',
        'absent_without_leave_days',
        'late_am_minutes',
        'late_pm_minutes',
        'undertime_am_minutes',
        'undertime_pm_minutes',
        'vl_earned',
        'sl_earned',
        'tardiness_equivalent_days',
        'uploaded_by',

    ];
    protected $casts = [
        'vl_earned'                  => 'decimal:3',
        'sl_earned'                  => 'decimal:3',
        'tardiness_equivalent_days'  => 'decimal:3',
    ];
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
