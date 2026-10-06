<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resident_drafts', function (Blueprint $table) {
            $table->string('occupation', 150)->nullable();
            $table->string('employment_status', 50)->nullable();
            $table->string('highest_education_level', 50)->nullable();
            $table->string('education_status', 50)->nullable();
            $table->string('disability_type', 150)->nullable();
            $table->string('ethnicity', 100)->nullable();
            foreach (['is_pwd', 'is_ofw', 'is_solo_parent', 'is_osy', 'is_osc', 'is_ip'] as $field) {
                $table->boolean($field)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('resident_drafts', fn (Blueprint $table) => $table->dropColumn([
            'occupation', 'employment_status', 'highest_education_level', 'education_status',
            'disability_type', 'ethnicity', 'is_pwd', 'is_ofw', 'is_solo_parent', 'is_osy', 'is_osc', 'is_ip',
        ]));
    }
};
