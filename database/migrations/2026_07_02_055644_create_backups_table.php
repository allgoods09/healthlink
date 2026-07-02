<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            
            // Backup file information
            $table->string('filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable(); // In bytes
            
            // Backup metadata
            $table->enum('backup_type', ['full', 'schema_only', 'data_only'])
                  ->default('full');
            
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])
                  ->default('pending');
            
            // Who generated it
            $table->foreignId('generated_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            // Storage location
            $table->enum('storage_location', ['local', 'external', 'cloud'])
                  ->default('local');
            
            // Additional context
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // DB size, tables count, etc.
            
            // Retention
            $table->timestamp('expires_at')->nullable(); // When backup can be deleted
            
            $table->timestamps();
            
            // Indexes for management
            $table->index('status');
            $table->index('backup_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};