<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('month');
            $table->year('year');
            $table->index(['month', 'year']);
            $table->unique(['employee_id', 'month', 'year']);
            $table->integer('total_working_days');
            $table->integer('absent_with_leave_days')->default(0);
            $table->integer('absent_without_leave_days')->default(0);
            $table->integer('late_am_minutes')->default(0);
            $table->integer('late_pm_minutes')->default(0);
            $table->integer('undertime_am_minutes')->default(0);
            $table->integer('undertime_pm_minutes')->default(0);
            $table->decimal('vl_earned', 8, 3)->nullable();
            $table->decimal('sl_earned', 8, 3)->nullable();
            $table->decimal('tardiness_equivalent_days', 8, 3)->nullable();
            // $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
