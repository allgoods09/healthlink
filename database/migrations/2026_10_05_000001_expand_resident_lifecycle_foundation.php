<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->enum('resident_status', ['active', 'deceased', 'relocated', 'moved_out'])
                ->default('active')->change();
            $table->unsignedBigInteger('lifecycle_version')->default(0)->after('resident_status');
        });
    }

    public function down(): void
    {
        // Check physical rows, including archives, before issuing any destructive DDL.
        if (DB::table('residents')->where('resident_status', 'moved_out')->exists()
            || DB::table('residents')->where('lifecycle_version', '!=', 0)->exists()) {
            throw new RuntimeException('Cannot roll back lifecycle foundation while MOVED_OUT records or non-zero lifecycle versions exist.');
        }

        Schema::table('residents', function (Blueprint $table): void {
            $table->enum('resident_status', ['active', 'deceased', 'relocated'])
                ->default('active')->change();
            $table->dropColumn('lifecycle_version');
        });
    }
};
