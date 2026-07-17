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
        Schema::create('leave_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('application_to', ['permanent', 'casual', 'elected', 'job_order', 'all'])->default('all');
            $table->boolean('can_carry_over')->default(false);
            $table->boolean('can_monetize')->default(false);
            $table->decimal('fixed_days', 8, 2)->nullable();
            $table->decimal('monthly_credit', 8, 2)->nullable();
            $table->enum('credit_type', ['fixed', 'monthly'])->default('fixed');
            $table->text('description')->nullable();
            // Add this column: allows you to set a cap on straight days (e.g., 3.00)
            // $table->decimal('max_consecutive_days', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_configurations');
    }
};
