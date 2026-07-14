<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->string('draft_reference_code', 30)->unique();
            $table->string('household_address');
            $table->string('drinking_water_source', 100)->nullable();
            $table->boolean('has_sanitary_toilet')->nullable();
            $table->string('sanitary_toilet_type', 100)->nullable();
            $table->boolean('is_social_aid_beneficiary')->default(false);
            $table->enum('draft_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('approved_household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'draft_status']);
            $table->index(['submitted_by_user_id', 'draft_status']);
        });

        Schema::create('resident_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_draft_id')->constrained('household_drafts')->cascadeOnDelete();
            $table->string('philsys_card_no', 50)->nullable();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->date('birth_date');
            $table->string('birth_place', 255);
            $table->enum('sex', ['Male', 'Female']);
            $table->string('civil_status', 50);
            $table->string('citizenship', 100)->default('Filipino');
            $table->string('religion', 100)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('email_address', 100)->nullable();
            $table->string('relationship_to_head', 100);
            $table->boolean('is_household_head_candidate')->default(false);
            $table->text('draft_notes')->nullable();
            $table->foreignId('approved_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->timestamps();

            $table->index(['household_draft_id', 'birth_date']);
        });

        Schema::create('profile_update_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->json('current_snapshot')->nullable();
            $table->json('proposed_changes');
            $table->text('request_reason')->nullable();
            $table->enum('request_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'request_status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('triage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('purok_id')->constrained('puroks')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consumed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('triage_status', ['pending', 'reviewed', 'closed'])->default('pending');
            $table->dateTime('measured_at');
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->decimal('temperature_celsius', 4, 1)->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('blood_glucose_mg_dl', 6, 1)->nullable();
            $table->text('triage_notes')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'triage_status']);
            $table->index(['resident_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_records');
        Schema::dropIfExists('profile_update_requests');
        Schema::dropIfExists('resident_drafts');
        Schema::dropIfExists('household_drafts');
    }
};
