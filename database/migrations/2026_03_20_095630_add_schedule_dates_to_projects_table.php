<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('project_manager_id');
            $table->date('end_date')->nullable()->after('start_date');

            $table->index(['start_date', 'end_date'], 'projects_schedule_window_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_schedule_window_idx');
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
