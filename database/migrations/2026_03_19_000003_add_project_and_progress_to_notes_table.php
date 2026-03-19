<?php

use App\Models\Note;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
        });

        $userIds = DB::table('notes')->select('user_id')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $projectId = DB::table('projects')->insertGetId([
                'user_id' => $userId,
                'name' => 'General',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('notes')
                ->where('user_id', $userId)
                ->update([
                    'project_id' => $projectId,
                    'progress' => DB::raw("CASE
                        WHEN status = '" . Note::STATUS_DONE . "' THEN 100
                        WHEN status = '" . Note::STATUS_IN_PROGRESS . "' THEN 50
                        ELSE 0
                    END"),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('progress');
        });
    }
};
