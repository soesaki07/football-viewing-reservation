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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('name_ja')->nullable()->after('short_name');
            $table->string('short_name_ja', 100)->nullable()->after('name_ja');
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->string('name_ja')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['name_ja', 'short_name_ja']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('name_ja');
        });
    }
};
