<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            
            // Unique key identifier (e.g., 'rate_limit_attempts')
            $table->string('key')->unique();
            
            // Value storage (JSON for complex, text for simple)
            $table->text('value');
            
            // Categorization for organization
            $table->string('group')->default('general');
            
            // Human-readable description
            $table->text('description')->nullable();
            
            // Control flags
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Index for faster lookups by group
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};