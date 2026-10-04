<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 64);
            $table->string('event_key', 100);
            $table->date('effective_date')->nullable();
            $table->dateTime('recorded_at', 6);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name_snapshot')->nullable();
            $table->string('actor_role_snapshot', 50)->nullable();
            foreach (['source', 'destination'] as $side) {
                $table->foreignId($side.'_barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
                $table->foreignId($side.'_purok_id')->nullable()->constrained('puroks')->nullOnDelete();
                $table->foreignId($side.'_household_id')->nullable()->constrained('households')->nullOnDelete();
                $table->string($side.'_barangay_name_snapshot', 100)->nullable();
                $table->integer($side.'_purok_number_snapshot')->nullable();
                $table->string($side.'_purok_name_snapshot', 100)->nullable();
                $table->string($side.'_household_no_snapshot', 50)->nullable();
            }
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->string('provenance', 100)->nullable();
            $table->unique(['resident_id', 'event_key'], 'resident_lifecycle_event_key_unique');
            $table->index(['resident_id', 'recorded_at', 'id'], 'resident_lifecycle_timeline_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('resident_lifecycle_events') && DB::table('resident_lifecycle_events')->exists()) {
            throw new RuntimeException('Cannot roll back resident lifecycle events while history exists.');
        }
        Schema::dropIfExists('resident_lifecycle_events');
    }
};
