<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_campaign_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('campaign_type', 40)->default('opt_plus');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'campaign_type', 'is_active'], 'ncp_barangay_type_active_idx');
        });

        Schema::create('opt_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('campaign_period_id')->nullable()->constrained('nutrition_campaign_periods')->nullOnDelete();
            $table->foreignId('measured_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('measurement_date');
            $table->unsignedSmallInteger('age_in_months');
            $table->enum('sex_snapshot', ['Male', 'Female']);
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('height_cm', 5, 2);
            $table->enum('measurement_posture', ['standing', 'recumbent']);
            $table->decimal('weight_for_age_z_score', 6, 3)->nullable();
            $table->string('weight_for_age_status', 50)->nullable();
            $table->decimal('height_for_age_z_score', 6, 3)->nullable();
            $table->string('height_for_age_status', 50)->nullable();
            $table->decimal('weight_for_length_height_z_score', 6, 3)->nullable();
            $table->string('weight_for_length_height_status', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'measurement_date'], 'opt_barangay_measured_idx');
            $table->index(['resident_id', 'measurement_date'], 'opt_resident_measured_idx');
        });

        Schema::create('child_nutrition_assessment_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('purok_id')->constrained('puroks')->cascadeOnDelete();
            $table->foreignId('flagged_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_measurement_id')->nullable()->constrained('opt_measurements', 'id', 'cnf_resolved_measure_fk')->nullOnDelete();
            $table->enum('flag_status', ['open', 'closed'])->default('open');
            $table->text('flag_reason')->nullable();
            $table->timestamp('flagged_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'flag_status'], 'cnf_barangay_status_idx');
            $table->index(['resident_id', 'flag_status'], 'cnf_resident_status_idx');
        });

        Schema::create('maternal_nutrition_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->unique()->constrained('residents')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_currently_pregnant')->default(false);
            $table->boolean('is_currently_lactating')->default(false);
            $table->date('expected_delivery_date')->nullable();
            $table->text('current_risk_notes')->nullable();
            $table->timestamp('last_status_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maternal_nutrition_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->date('event_date');
            $table->unsignedSmallInteger('gestational_age_weeks')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['resident_id', 'event_date'], 'mnh_resident_event_idx');
        });

        Schema::create('infant_feeding_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('mother_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('observed_on');
            $table->enum('feeding_method', ['exclusive_breastfeeding', 'mixed_feeding', 'formula']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['resident_id', 'observed_on'], 'ifl_resident_observed_idx');
        });

        Schema::create('micronutrient_supplementation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('distributed_by_user_id')->constrained('users', 'id', 'msl_distributed_user_fk')->cascadeOnDelete();
            $table->date('administered_on');
            $table->enum('supplement_type', ['vitamin_a', 'iron_drops', 'mnp']);
            $table->enum('recipient_category', ['toddler', 'pregnant_woman', 'lactating_mother']);
            $table->string('dose_description', 120)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'administered_on'], 'msl_barangay_admin_idx');
        });

        Schema::create('feeding_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('campaign_period_id')->nullable()->constrained('nutrition_campaign_periods')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('program_status', 30)->default('planned');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'program_status'], 'fp_barangay_status_idx');
        });

        Schema::create('feeding_program_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feeding_program_id')->constrained('feeding_programs')->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('enrolled_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('enrolled_on');
            $table->decimal('baseline_weight_kg', 5, 2)->nullable();
            $table->string('baseline_nutritional_status', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->unique(['feeding_program_id', 'resident_id'], 'feeding_program_resident_unique');
        });

        Schema::create('feeding_program_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('feeding_program_enrollments')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->enum('attendance_status', ['present', 'absent', 'excused'])->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'attendance_date'], 'feeding_attendance_unique');
        });

        Schema::create('feeding_program_progress_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('feeding_program_enrollments')->cascadeOnDelete();
            $table->foreignId('logged_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('logged_on');
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'logged_on'], 'fpp_enrollment_logged_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeding_program_progress_logs');
        Schema::dropIfExists('feeding_program_attendances');
        Schema::dropIfExists('feeding_program_enrollments');
        Schema::dropIfExists('feeding_programs');
        Schema::dropIfExists('micronutrient_supplementation_logs');
        Schema::dropIfExists('infant_feeding_logs');
        Schema::dropIfExists('maternal_nutrition_histories');
        Schema::dropIfExists('maternal_nutrition_profiles');
        Schema::dropIfExists('child_nutrition_assessment_flags');
        Schema::dropIfExists('opt_measurements');
        Schema::dropIfExists('nutrition_campaign_periods');
    }
};
