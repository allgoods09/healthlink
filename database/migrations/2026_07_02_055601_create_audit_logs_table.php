<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Who performed the action (nullable for failed logins)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            // What happened (using ENUM for strict control)
            $table->enum('event_type', [
                'login', 'logout', 'failed_login', 'password_reset',
                'created', 'updated', 'deleted', 'restored', 'force_deleted',
                'synced', 'backup_generated', 'backup_restored',
                'token_revoked', 'status_toggled', 'exported'
            ]);
            
            // Human-readable description
            $table->text('event_description');
            
            // Which model was affected (polymorphic-like but stored as strings)
            $table->string('model_type')->nullable(); // e.g., 'App\Models\User'
            $table->unsignedBigInteger('model_id')->nullable(); // e.g., 123
            
            // Before and after snapshots for data mutations
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            
            // Request metadata for forensic analysis
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Additional context
            $table->json('metadata')->nullable(); // Extra data like route, method, etc.
            
            $table->timestamps();
            
            // Optimized indexes for fast retrieval
            $table->index('user_id');
            $table->index('event_type');
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};