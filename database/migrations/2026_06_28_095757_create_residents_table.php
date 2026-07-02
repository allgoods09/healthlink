<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            
            // Belongs to a specific household
            $table->foreignId('household_id')
                  ->constrained('households')
                  ->onDelete('cascade');
            
            // PhilSys ID - optional but unique if provided
            $table->string('philsys_card_no', 50)->nullable()->unique();
            
            // Personal Information (from Individual Records of Barangay Inhabitant)
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 20)->nullable(); // Jr., Sr., III, etc.
            
            // Demographic details
            $table->date('birth_date');
            $table->string('birth_place', 255);
            $table->enum('sex', ['Male', 'Female']);
            $table->string('civil_status', 50);
            $table->string('citizenship', 100)->default('Filipino');
            $table->string('religion', 100)->nullable();
            
            // Contact information
            $table->string('contact_number', 20)->nullable();
            $table->string('email_address', 100)->nullable();
            
            // Household role
            $table->string('relationship_to_head', 100);
            
            // Operational status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // CRITICAL: Composite unique to prevent duplicate entries
            // across offline BHW syncs when PhilSys ID is absent
            $table->unique(
                ['first_name', 'last_name', 'birth_date', 'household_id'],
                'resident_identity_unique'
            );
            
            // Optimized indexes for search operations
            $table->index(['last_name', 'first_name', 'birth_date']);
            $table->index('philsys_card_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};