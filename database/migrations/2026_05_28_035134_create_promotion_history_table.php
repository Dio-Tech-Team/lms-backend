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
        Schema::create('employment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('previous_position')->nullable();
            $table->string('new_position');
            $table->enum('previous_employment_status', ['permanent', 'casual', 'elected', 'job_order'])->default('job_order');
            $table->enum('new_employment_status', ['permanent', 'casual', 'elected', 'job_order']);
            $table->date('effective_date');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_history');
    }
};
