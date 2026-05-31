<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionHistory extends Model
{
    protected $table = 'promotion_history'; // ← add this

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
