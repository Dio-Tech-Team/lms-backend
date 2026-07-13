<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;
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
    protected $casts = [
        'date_hired' => 'date:Y-m-d',
        'birthdate'  => 'date:Y-m-d',
        'is_active'  => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employment_history()
    {
        return $this->hasMany(EmploymentHistory::class);
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function leaveApplications()
    {
        return $this->hasMany(LeaveApplication::class);
    }
    public function leaveCredits()
    {
        return $this->hasMany(LeaveCredit::class);
    }

    public function leaveRecords()
    {
        return $this->hasMany(LeaveRecord::class);
    }
}
