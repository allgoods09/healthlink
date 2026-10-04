<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_code_sequences', function (Blueprint $table): void {
            // Issuance namespaces survive deletion of their former barangay records.
            $table->unsignedBigInteger('origin_barangay_id')->primary();
            $table->unsignedBigInteger('last_value')->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('resident_code_sequences') && DB::table('resident_code_sequences')->exists()) {
            throw new RuntimeException('Cannot drop permanent Resident code sequence history. Retain it and disable writes instead.');
        }
        Schema::dropIfExists('resident_code_sequences');
    }
};
