<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_records', function (Blueprint $table) {
            $table->id();
            
            // Reference to the original record
            $table->string('original_table'); // 'residents', 'households', etc.
            $table->unsignedBigInteger('original_id');
            
            // Complete snapshot of the record at time of archival
            $table->json('data_snapshot');
            
            // Archival metadata
            $table->foreignId('archived_by')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            $table->text('archiving_reason')->nullable();
            
            // Dual-phase lifecycle: Archive first, then optionally purge
            $table->boolean('is_purged')->default(false);
            $table->timestamp('purged_at')->nullable();
            $table->foreignId('purged_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes for retrieval
            $table->index(['original_table', 'original_id']);
            $table->index('archived_by');
            $table->index('is_purged');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_records');
    }
};