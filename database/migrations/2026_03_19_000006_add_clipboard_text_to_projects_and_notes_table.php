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
            $table->text('clipboard_text')->nullable()->after('description');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->text('clipboard_text')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('clipboard_text');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('clipboard_text');
        });
    }
};
