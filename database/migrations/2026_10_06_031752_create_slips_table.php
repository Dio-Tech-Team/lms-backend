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
        Schema::create('slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['official', 'personal']);
            $table->date('date');
            $table->time('time_out');
            $table->time('time_in');
            $table->unsignedSmallInteger('minutes');
            $table->decimal('equivalent_days', 6, 3);           // CSC table conversion
            $table->decimal('lwop_days', 6, 3)->default(0);     // part VL couldn't absorb
            $table->string('reason')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('leave_records', function (Blueprint $table) {
            $table->foreignId('slip_id')->nullable()->after('attendance_id')
                ->constrained('slips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('slip_id');
        });
        Schema::dropIfExists('slips');
    }
};
