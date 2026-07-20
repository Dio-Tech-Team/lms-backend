<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Department extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
