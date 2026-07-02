<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_socio_economic_profiles', function (Blueprint $table) {
            $table->foreignId('resident_id')->primary()->constrained('residents')->onDelete('cascade');
            $table->string('occupation', 150)->nullable();
            $table->enum('employment_status', ['Employed', 'Unemployed', 'N/A'])->default('N/A');
            $table->enum('highest_education_level', ['None', 'Elementary', 'High School', 'College', 'Post Grad', 'Vocational'])->default('None');
            $table->enum('education_status', ['Graduate', 'Undergraduate', 'N/A'])->default('N/A');
            $table->boolean('is_pwd')->default(false);
            $table->string('disability_type', 150)->nullable();
            $table->boolean('is_ofw')->default(false);
            $table->boolean('is_solo_parent')->default(false);
            $table->boolean('is_osy')->default(false); 
            $table->boolean('is_osc')->default(false); 
            $table->boolean('is_ip')->default(false);  
            $table->string('ethnicity', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_socio_economic_profiles');
    }
};