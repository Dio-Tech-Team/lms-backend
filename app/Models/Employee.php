<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'contact_number',
        'id_number',
        'employment_status',
        'position',
        'date_hired',
        'is_active'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function promotionHistory()
    {
        return $this->hasMany(PromotionHistory::class);
    }
}
