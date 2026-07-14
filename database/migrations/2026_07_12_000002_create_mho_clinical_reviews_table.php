<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mho_clinical_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('reviewed_at');
            $table->text('final_assessment')->nullable();
            $table->text('diagnostic_override')->nullable();
            $table->text('prescription_notes')->nullable();
            $table->string('referral_destination')->nullable();
            $table->string('final_disposition')->nullable();
            $table->text('return_instructions')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->unique('clinical_encounter_id', 'mho_reviews_encounter_unique');
            $table->index(['reviewed_by_user_id', 'reviewed_at'], 'mho_reviews_user_date_idx');
            $table->index('reviewed_at', 'mho_reviews_reviewed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mho_clinical_reviews');
    }
};
