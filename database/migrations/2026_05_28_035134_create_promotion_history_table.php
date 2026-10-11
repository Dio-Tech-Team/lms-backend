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
            $table->enum('previous_employment_status', ['permanent', 'casual', 'elected', 'job_order', 'resigned', 'retired'])->nullable();
            $table->enum('new_employment_status', ['permanent', 'casual', 'elected', 'job_order', 'resigned', 'retired']);
            $table->date('effective_date');
            $table->text('remarks')->nullable();
            // Late casual renewal: days without appointment and the VL/SL each lost for it
            $table->unsignedSmallInteger('gap_days')->default(0);
            $table->decimal('credits_lost', 8, 3)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_history');
    }
};
