<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_certificates', function (Blueprint $table) {
            // Three 100-character name parts, a 20-character suffix, and separating spaces.
            $table->string('signatory_name_at_issuance', 323)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('barangay_certificates', function (Blueprint $table) {
            $table->dropColumn('signatory_name_at_issuance');
        });
    }
};
