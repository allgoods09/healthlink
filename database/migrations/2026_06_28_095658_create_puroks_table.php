<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puroks', function (Blueprint $table) {
            $table->id();
            
            // Belongs to a specific barangay
            $table->foreignId('barangay_id')
                  ->constrained('barangays')
                  ->onDelete('cascade');
            
            // Purok numbering: Purok 1, Purok 2, etc. (unique per barangay)
            $table->integer('purok_number');
            
            // Optional friendly name (e.g., "Purok Kabataang Barangay")
            $table->string('purok_name', 100)->nullable();
            
            // Operational status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Composite unique: Each barangay can have Purok 1 only once
            $table->unique(['barangay_id', 'purok_number'], 'barangay_purok_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puroks');
    }
};