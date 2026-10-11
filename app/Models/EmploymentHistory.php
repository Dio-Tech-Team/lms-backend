<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;

class EmploymentHistory extends Model
{
    // Explicitly define the table name to match your migration
    protected $table = 'employment_history';

    protected $fillable = [
        'employee_id',
        'previous_position',
        'new_position',
        'previous_employment_status',
        'new_employment_status',
        'effective_date', // Synced with migration
        'remarks',
        'gap_days',
        'credits_lost',
    ];

    protected $casts = [
        'effective_date' => 'date:Y-m-d',
        'gap_days'       => 'integer',
        'credits_lost'   => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
