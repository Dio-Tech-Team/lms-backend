<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;

class PromotionHistory extends Model
{
    protected $fillable = [
        'employee_id',
        'previous_position',
        'new_position',
        'promotion_date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
