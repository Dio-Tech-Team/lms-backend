<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionHistory extends Model
{
    protected $fillable = [
        'employee_id',
        'previous_position',
        'new_position',
        'promotion_date',
    ];
}
