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
        Schema::table('notes', function (Blueprint $table) {
            $table->string('scope')->default('general')->after('user_id');
            $table->unsignedSmallInteger('work_year')->nullable()->after('scope');
            $table->string('work_week_label')->nullable()->after('work_year');
            $table->unsignedTinyInteger('work_week_number')->nullable()->after('work_week_label');
            $table->string('owner_name_raw')->nullable()->after('work_week_number');
            $table->string('category')->nullable()->after('owner_name_raw');
            $table->string('linked_goal')->nullable()->after('category');
            $table->string('priority_code')->nullable()->after('linked_goal');
            $table->string('workload_status')->nullable()->after('priority_code');
            $table->decimal('estimated_time_hours', 8, 2)->nullable()->after('workload_status');
            $table->boolean('moved_to_next_week')->default(false)->after('estimated_time_hours');
            $table->string('replaced_task')->nullable()->after('moved_to_next_week');
            $table->date('source_date_added')->nullable()->after('replaced_task');
            $table->string('import_batch')->nullable()->after('source_date_added');
            $table->string('import_fingerprint', 64)->nullable()->after('import_batch');

            $table->index(['user_id', 'scope', 'work_year', 'work_week_number'], 'notes_workload_scope_week_idx');
            $table->index(['user_id', 'scope', 'workload_status'], 'notes_workload_scope_status_idx');
            $table->unique('import_fingerprint', 'notes_import_fingerprint_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique('notes_import_fingerprint_unique');
            $table->dropIndex('notes_workload_scope_week_idx');
            $table->dropIndex('notes_workload_scope_status_idx');
            $table->dropColumn([
                'scope',
                'work_year',
                'work_week_label',
                'work_week_number',
                'owner_name_raw',
                'category',
                'linked_goal',
                'priority_code',
                'workload_status',
                'estimated_time_hours',
                'moved_to_next_week',
                'replaced_task',
                'source_date_added',
                'import_batch',
                'import_fingerprint',
            ]);
        });
    }
};
