<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                ->default('approved')
                ->after('role');
            $table->enum('registered_via', ['admin', 'self'])
                ->default('admin')
                ->after('approval_status');
            $table->enum('requested_role', ['admin', 'mho', 'phn', 'secretary', 'bns', 'bhw'])
                ->nullable()
                ->after('registered_via');
            $table->foreignId('requested_barangay_id')
                ->nullable()
                ->after('assigned_barangay_id')
                ->constrained('barangays')
                ->nullOnDelete();
            $table->foreignId('requested_purok_id')
                ->nullable()
                ->after('assigned_purok_id')
                ->constrained('puroks')
                ->nullOnDelete();
            $table->text('approval_notes')->nullable()->after('requested_purok_id');
            $table->timestamp('approved_at')->nullable()->after('approval_notes');
            $table->foreignId('approved_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')
                ->nullable()
                ->after('rejected_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('users')->update([
            'approval_status' => 'approved',
            'registered_via' => 'admin',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn('rejected_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropColumn('approval_notes');
            $table->dropConstrainedForeignId('requested_purok_id');
            $table->dropConstrainedForeignId('requested_barangay_id');
            $table->dropColumn('requested_role');
            $table->dropColumn('registered_via');
            $table->dropColumn('approval_status');
        });
    }
};
