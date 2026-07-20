<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('app_scope')->default('bhw');
            $table->string('platform')->default('android');
            $table->string('version_name');
            $table->unsignedInteger('version_code');
            $table->string('release_title')->nullable();
            $table->longText('release_notes')->nullable();
            $table->string('artifact_source')->default('upload');
            $table->string('artifact_path')->nullable();
            $table->text('artifact_url')->nullable();
            $table->string('update_mode')->default('optional');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rolled_back_from_release_id')->nullable()->constrained('mobile_app_releases')->nullOnDelete();
            $table->timestamps();

            $table->unique(['app_scope', 'platform', 'version_code'], 'mobile_rel_scope_platform_version_unique');
            $table->index(['app_scope', 'platform', 'status'], 'mobile_rel_scope_platform_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_releases');
    }
};
