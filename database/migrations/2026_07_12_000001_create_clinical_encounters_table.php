<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('triage_record_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purok_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attended_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('encounter_source', ['triage', 'walk_in'])->default('triage');
            $table->dateTime('encountered_at');
            $table->text('consultation_notes')->nullable();
            $table->text('working_impression')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('disposition')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->enum('follow_up_status', ['due', 'completed', 'missed', 'rescheduled'])->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->text('medicines_administered')->nullable();
            $table->text('lifestyle_advice')->nullable();
            $table->text('referral_notes')->nullable();
            $table->text('return_instructions')->nullable();
            $table->boolean('is_escalated_to_mho')->default(false);
            $table->text('escalation_notes')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'encountered_at'], 'clin_enc_brgy_date_idx');
            $table->index(['follow_up_status', 'follow_up_date'], 'clin_enc_follow_idx');
            $table->index(['is_escalated_to_mho', 'closed_at'], 'clin_enc_mho_idx');
            $table->index(['encounter_source', 'encountered_at'], 'clin_enc_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_encounters');
    }
};
