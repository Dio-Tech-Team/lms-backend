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
        'surname',
        'id_number',
        'birthdate',
        'place_of_birth',
        'sex',
        'civil_status',
        'height',
        'weight',
        'bloodtype',
        'highest_educational_attainment',
        'residential_address',
        'contact_number',
        'umid_id',
        'pagibig_id',
        'philhealth_number',
        'psn_number',
        'tin_number',
        'employment_status',
        'position',
        'date_hired',
        'is_active',
    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function promotion_history()
    {
        return $this->hasMany(EmploymentHistory::class);
    }
}
