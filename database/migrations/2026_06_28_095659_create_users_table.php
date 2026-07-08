<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // Core Authentication Credentials
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // 6-Role Engine Strategy - Using ENUM for strict control
            $table->enum('role', ['admin', 'mho', 'phn', 'secretary', 'bns', 'bhw'])
                  ->default('bhw');
            
            // Enterprise Scaling Boundary Assignments
            // NULL for global roles: admin, mho, phn
            $table->foreignId('assigned_barangay_id')
                  ->nullable()
                  ->constrained('barangays')
                  ->onDelete('set null');
            
            $table->foreignId('assigned_purok_id')
                  ->nullable()
                  ->constrained('puroks')
                  ->onDelete('set null');
            
            // Operational status tracker for local management
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity')->nullable()->default(now());
            
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // For historical record integrity
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};