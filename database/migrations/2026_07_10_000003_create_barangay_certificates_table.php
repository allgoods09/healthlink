<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')
                ->constrained('barangays')
                ->cascadeOnDelete();
            $table->enum('certificate_type', ['barangay_clearance', 'certificate_of_indigency']);
            $table->enum('recipient_type', ['resident', 'household']);
            $table->foreignId('resident_id')
                ->nullable()
                ->constrained('residents')
                ->nullOnDelete();
            $table->foreignId('household_id')
                ->nullable()
                ->constrained('households')
                ->nullOnDelete();
            $table->string('certificate_no', 64)->unique();
            $table->string('issued_to_name', 255);
            $table->string('purpose', 255);
            $table->text('remarks')->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'certificate_type']);
            $table->index(['barangay_id', 'issued_at']);
            $table->index('recipient_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_certificates');
    }
};
