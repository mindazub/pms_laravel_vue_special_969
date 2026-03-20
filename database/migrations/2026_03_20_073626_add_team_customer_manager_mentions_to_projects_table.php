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
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->json('mentions')->nullable()->after('attachments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_manager_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn('mentions');
        });
    }
};
