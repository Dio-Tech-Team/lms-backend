<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;

class PromotionHistory extends Model
{
    // Explicitly define the table name to match your migration
    protected $table = 'promotion_history';

    protected $fillable = [
        'employee_id',
        'previous_position',
        'new_position',
        'previous_employment_status',
        'new_employment_status',
        'effective_date', // Synced with migration
        'remarks',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
