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
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('leave_configuration_id')->constrained('leave_configurations')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_applied', 8, 3);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'cancelled', 'rejected'])->default('pending');
            $table->index('status');
            $table->foreignId('filed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
