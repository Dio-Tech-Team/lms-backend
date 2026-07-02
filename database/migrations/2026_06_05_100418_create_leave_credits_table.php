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
        Schema::create('leave_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_configuration_id')->constrained()->onDelete('cascade');
            $table->decimal('total_credits', 8, 3)->default(0);
            $table->decimal('used_credits', 8, 3)->default(0);
            $table->decimal('remaining_balance', 8, 3)->default(0);
            $table->year('year');
            $table->index('year');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_credits');
    }
};
