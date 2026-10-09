<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('accrual_month');   // first day of the month — dedup key
            $table->date('accrual_date');    // actual anniversary date credited
            $table->decimal('vl_earned', 8, 3);
            $table->decimal('sl_earned', 8, 3);
            $table->timestamps();

            $table->unique(['employee_id', 'accrual_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_accruals');
    }
};
