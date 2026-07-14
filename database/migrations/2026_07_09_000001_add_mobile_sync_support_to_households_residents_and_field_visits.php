<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table): void {
            $table->uuid('mobile_uuid')->nullable()->unique()->after('id');
        });

        Schema::table('residents', function (Blueprint $table): void {
            $table->uuid('mobile_uuid')->nullable()->unique()->after('id');
        });

        Schema::create('field_visits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('mobile_uuid')->unique();
            $table->foreignId('household_id')
                ->constrained('households')
                ->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('visited_at');
            $table->text('notes')->nullable();
            $table->json('photos')->nullable();
            $table->string('source')->default('mobile');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['household_id', 'visited_at']);
            $table->index(['recorded_by_user_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visits');

        Schema::table('residents', function (Blueprint $table): void {
            $table->dropUnique(['mobile_uuid']);
            $table->dropColumn('mobile_uuid');
        });

        Schema::table('households', function (Blueprint $table): void {
            $table->dropUnique(['mobile_uuid']);
            $table->dropColumn('mobile_uuid');
        });
    }
};
