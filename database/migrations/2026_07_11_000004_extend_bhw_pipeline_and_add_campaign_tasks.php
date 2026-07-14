<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table): void {
            $table->string('garbage_disposal_method', 40)->nullable()->after('sanitary_toilet_type');
            $table->boolean('has_backyard_garden')->nullable()->after('garbage_disposal_method');
            $table->string('housing_material_type', 40)->nullable()->after('has_backyard_garden');
        });

        Schema::table('household_drafts', function (Blueprint $table): void {
            $table->string('garbage_disposal_method', 40)->nullable()->after('sanitary_toilet_type');
            $table->boolean('has_backyard_garden')->nullable()->after('garbage_disposal_method');
            $table->string('housing_material_type', 40)->nullable()->after('has_backyard_garden');
        });

        Schema::create('community_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->string('title', 120);
            $table->string('campaign_type', 40)->default('general');
            $table->date('scheduled_for');
            $table->text('description')->nullable();
            $table->enum('campaign_status', ['active', 'completed', 'archived'])->default('active');
            $table->timestamps();

            $table->index(['barangay_id', 'scheduled_for'], 'cc_barangay_schedule_idx');
            $table->index(['barangay_id', 'campaign_status'], 'cc_barangay_status_idx');
        });

        Schema::create('community_campaign_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_campaign_id')->constrained('community_campaigns')->cascadeOnDelete();
            $table->foreignId('assigned_bhw_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->enum('assignment_status', ['pending', 'completed', 'skipped'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('field_notes')->nullable();
            $table->timestamps();

            $table->index(['assigned_bhw_user_id', 'assignment_status'], 'cca_bhw_status_idx');
            $table->index(['community_campaign_id', 'assignment_status'], 'cca_campaign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_campaign_assignments');
        Schema::dropIfExists('community_campaigns');

        Schema::table('household_drafts', function (Blueprint $table): void {
            $table->dropColumn([
                'garbage_disposal_method',
                'has_backyard_garden',
                'housing_material_type',
            ]);
        });

        Schema::table('households', function (Blueprint $table): void {
            $table->dropColumn([
                'garbage_disposal_method',
                'has_backyard_garden',
                'housing_material_type',
            ]);
        });
    }
};
