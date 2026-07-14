<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->enum('resident_status', ['active', 'deceased', 'relocated'])
                ->default('active')
                ->after('relationship_to_head');
            $table->date('moved_in_at')->nullable()->after('resident_status');
            $table->date('moved_out_at')->nullable()->after('moved_in_at');
            $table->date('date_of_death')->nullable()->after('moved_out_at');
            $table->text('status_notes')->nullable()->after('date_of_death');

            $table->index('resident_status');
            $table->index('moved_in_at');
            $table->index('moved_out_at');
            $table->index('date_of_death');
        });

        DB::table('residents')->update([
            'resident_status' => 'active',
        ]);
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex(['resident_status']);
            $table->dropIndex(['moved_in_at']);
            $table->dropIndex(['moved_out_at']);
            $table->dropIndex(['date_of_death']);

            $table->dropColumn([
                'resident_status',
                'moved_in_at',
                'moved_out_at',
                'date_of_death',
                'status_notes',
            ]);
        });
    }
};
