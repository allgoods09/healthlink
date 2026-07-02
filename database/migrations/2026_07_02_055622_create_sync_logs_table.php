<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            
            // Which BHW performed the sync
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            // Device information
            $table->string('device_name')->nullable();
            $table->string('device_model')->nullable();
            $table->string('app_version')->nullable();
            
            // Sync metrics
            $table->integer('records_synced')->default(0);
            $table->integer('payload_size')->nullable(); // In bytes
            $table->integer('sync_duration')->nullable(); // In milliseconds
            
            // Sync status
            $table->enum('status', ['success', 'failed', 'partial'])->default('success');
            $table->text('error_message')->nullable();
            
            // Network context
            $table->string('ip_address', 45)->nullable();
            $table->string('network_type')->nullable(); // wifi, mobile, offline
            
            // Sync metadata
            $table->json('sync_metadata')->nullable(); // Additional context
            
            $table->timestamps();
            
            // Indexes for monitoring dashboards
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};