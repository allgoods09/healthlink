<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('philpen_risk_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('mobile_uuid')->unique();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('mobile');
            $table->date('assessment_date');
            $table->unsignedTinyInteger('age_years')->nullable();
            $table->string('religion', 100)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('philhealth_number', 100)->nullable();
            $table->string('civil_status', 50)->nullable();
            $table->string('ethnicity', 100)->nullable();
            $table->string('pwd_id_number', 100)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('body_mass_index', 6, 2)->nullable();
            $table->decimal('waist_circumference_cm', 6, 2)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->string('employment_status', 50)->nullable();
            $table->string('ip_classification', 20)->nullable();
            $table->boolean('requires_immediate_referral')->default(false);
            $table->json('identity_snapshot')->nullable();
            $table->json('red_flags')->nullable();
            $table->json('past_medical_history')->nullable();
            $table->json('family_history')->nullable();
            $table->string('tobacco_use', 50)->nullable();
            $table->string('alcohol_consumption_status', 50)->nullable();
            $table->boolean('alcohol_binge_flag')->nullable();
            $table->boolean('physical_activity_met')->nullable();
            $table->boolean('high_risk_diet_weekly')->nullable();
            $table->string('blood_sugar_notes', 120)->nullable();
            $table->string('fbs_result', 50)->nullable();
            $table->string('rbs_result', 50)->nullable();
            $table->json('dm_symptoms')->nullable();
            $table->date('lipid_profile_date')->nullable();
            $table->string('total_cholesterol', 50)->nullable();
            $table->string('hdl', 50)->nullable();
            $table->string('ldl', 50)->nullable();
            $table->string('vldl', 50)->nullable();
            $table->string('triglycerides', 50)->nullable();
            $table->string('urinalysis_protein', 50)->nullable();
            $table->string('urinalysis_ketones', 50)->nullable();
            $table->date('urinalysis_date')->nullable();
            $table->json('chronic_respiratory_symptoms')->nullable();
            $table->boolean('lifestyle_modification')->nullable();
            $table->text('anti_hypertensive_medications')->nullable();
            $table->text('oral_hypoglycemic_medications')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['resident_id', 'assessment_date'], 'philpen_ra_res_date_idx');
            $table->index(['barangay_id', 'assessment_date'], 'philpen_ra_brgy_date_idx');
            $table->index(['recorded_by_user_id', 'assessment_date'], 'philpen_ra_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('philpen_risk_assessments');
    }
};
