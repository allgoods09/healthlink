<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            
            // Official geographic identifiers
            $table->string('name', 100);
            $table->string('psgc_code', 20)->unique()->comment('Philippine Standard Geographic Code');
            
            // Default to Tubigon, Bohol for Phase 1
            $table->string('municipality', 100)->default('Tubigon');
            $table->string('province', 100)->default('Bohol');
            $table->string('region', 50)->default('VII');
            
            // Operational status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure barangay names are unique within the municipality
            $table->unique(['name', 'municipality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};