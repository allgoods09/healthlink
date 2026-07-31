<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_officials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->string('role_key', 50);
            $table->string('official_title', 100);
            $table->string('official_name', 150)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['barangay_id', 'role_key']);
            $table->index(['barangay_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_officials');
    }
};
