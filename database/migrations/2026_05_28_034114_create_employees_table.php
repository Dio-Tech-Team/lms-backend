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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade')->index();

            // Personal Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('surname');
            $table->string('id_number')->unique();
            $table->date('birthdate')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->enum('sex', ['male', 'female']);
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated']);
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('bloodtype')->nullable();
            $table->enum('highest_educational_attainment', ['elementary', 'secondary', 'vocational', 'college', 'graduate']);

            // Contact & Address
            $table->string('residential_address')->nullable();
            $table->string('contact_number')->nullable();

            // Government IDs
            $table->string('umid_id')->nullable();
            $table->string('pagibig_id')->nullable();
            $table->string('philhealth_number')->nullable();
            $table->string('psn_number')->nullable();
            $table->string('tin_number')->nullable();

            // Employment Information
            $table->enum('employment_status', ['permanent', 'casual', 'elected', 'job_order', 'resigned', 'retired']);
            $table->enum('retirement_type', ['mandatory', 'optional'])->nullable();
            $table->string('position');
            $table->date('date_hired');
            // $table->boolean('is_active')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            // $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
