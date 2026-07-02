<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            
            // Belongs to a specific purok
            $table->foreignId('purok_id')
                  ->constrained('puroks')
                  ->onDelete('cascade');
            
            // Household number (e.g., "001", "1") - unique per purok
            $table->string('household_no', 50);
            
            // Full address of the household
            $table->text('household_address');
            
            // Social welfare indicators
            $table->boolean('is_social_aid_beneficiary')->default(false);
            
            // Operational status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Composite unique: Each purok can have Household No. 1 only once
            $table->unique(['purok_id', 'household_no'], 'purok_household_composite_unique');
            
            // Index for faster lookups
            $table->index('household_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};