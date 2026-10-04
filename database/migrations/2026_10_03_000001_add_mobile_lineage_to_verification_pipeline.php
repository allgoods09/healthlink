<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_drafts', function (Blueprint $table): void {
            $table->uuid('mobile_uuid')->nullable()->unique();
            $table->string('proposed_household_no', 50)->nullable();
            $table->foreignId('target_household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->unsignedInteger('mobile_revision')->default(0);
        });

        Schema::table('resident_drafts', function (Blueprint $table): void {
            $table->uuid('mobile_uuid')->nullable()->unique();
            $table->unsignedInteger('mobile_revision')->default(0);
        });

        Schema::table('profile_update_requests', function (Blueprint $table): void {
            $table->string('mobile_submission_key', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('profile_update_requests', fn (Blueprint $table) => $table->dropColumn('mobile_submission_key'));
        Schema::table('resident_drafts', fn (Blueprint $table) => $table->dropColumn(['mobile_uuid', 'mobile_revision']));
        Schema::table('household_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('target_household_id');
            $table->dropColumn(['mobile_uuid', 'proposed_household_no', 'mobile_revision']);
        });
    }
};
