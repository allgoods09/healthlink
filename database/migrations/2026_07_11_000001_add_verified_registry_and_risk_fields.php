<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table): void {
            $table->string('official_household_code', 30)->nullable()->after('id')->unique();
            $table->string('drinking_water_source', 100)->nullable()->after('household_address');
            $table->boolean('has_sanitary_toilet')->nullable()->after('drinking_water_source');
            $table->string('sanitary_toilet_type', 100)->nullable()->after('has_sanitary_toilet');
        });

        Schema::table('residents', function (Blueprint $table): void {
            $table->string('official_resident_code', 30)->nullable()->after('id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->dropUnique(['official_resident_code']);
            $table->dropColumn('official_resident_code');
        });

        Schema::table('households', function (Blueprint $table): void {
            $table->dropUnique(['official_household_code']);
            $table->dropColumn([
                'official_household_code',
                'drinking_water_source',
                'has_sanitary_toilet',
                'sanitary_toilet_type',
            ]);
        });
    }
};
