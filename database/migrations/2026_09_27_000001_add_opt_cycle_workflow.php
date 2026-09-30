<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_nutrition_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('caregiver_name', 255)->nullable();
            $table->string('caregiver_relationship', 80)->nullable();
            $table->timestamp('caregiver_confirmed_at')->nullable();
            $table->boolean('ip_membership')->nullable();
            $table->timestamp('ip_confirmed_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('opt_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('round', 20);
            $table->date('reference_date');
            $table->string('status', 30)->default('in_progress');
            $table->timestamp('roster_captured_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_note')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopening_reason')->nullable();
            $table->string('rules_version', 80)->default('pooc-pilot-v1');
            $table->string('source', 40)->default('registry_snapshot');
            $table->json('provenance')->nullable();
            $table->timestamps();
            $table->unique(['barangay_id', 'year', 'round'], 'opt_cycle_barangay_year_round_unique');
        });

        Schema::create('opt_cycle_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opt_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('resident_key');
            $table->string('resident_code')->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->date('birth_date');
            $table->string('sex', 20)->nullable();
            $table->string('barangay_name');
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('region')->nullable();
            $table->string('psgc_code')->nullable();
            $table->unsignedBigInteger('purok_key')->nullable();
            $table->string('purok_name')->nullable();
            $table->string('household_code')->nullable();
            $table->string('household_number')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('caregiver_resident_key')->nullable();
            $table->string('caregiver_name')->nullable();
            $table->string('caregiver_relationship', 80)->nullable();
            $table->timestamp('caregiver_confirmed_at')->nullable();
            $table->boolean('ip_membership')->nullable();
            $table->timestamp('ip_confirmed_at')->nullable();
            $table->string('ethnicity', 100)->nullable();
            $table->timestamps();
            $table->unique(['opt_cycle_id', 'resident_key']);
            $table->index(['opt_cycle_id', 'purok_key']);
        });

        Schema::table('opt_measurements', function (Blueprint $table): void {
            $table->foreignId('opt_cycle_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('assessment_source', 80)->nullable();
            $table->string('assessment_version', 80)->nullable();
            $table->text('assessment_error')->nullable();
            $table->dropForeign(['resident_id']);
            $table->dropForeign(['measured_by_user_id']);
        });
        Schema::table('opt_measurements', function (Blueprint $table): void {
            $table->unsignedBigInteger('resident_id')->nullable()->change();
            $table->unsignedBigInteger('measured_by_user_id')->nullable()->change();
            $table->foreign('resident_id')->references('id')->on('residents')->nullOnDelete();
            $table->foreign('measured_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('feeding_program_enrollments', function (Blueprint $table): void {
            $table->foreignId('baseline_opt_measurement_id')->nullable()->constrained('opt_measurements')->nullOnDelete();
            $table->json('baseline_provenance')->nullable();
        });
        Schema::table('child_nutrition_assessment_flags', function (Blueprint $table): void {
            $table->text('resolution_note')->nullable();
        });
    }

    public function down(): void
    {
        // Restoring cascading, non-null history links could destroy retained records.
        throw new LogicException('OPT cycle history requires a reviewed backup restore, not a destructive rollback.');
    }
};
