<?php

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
        Schema::create('favorite_teams', function (Blueprint $table) {
            $table->id()->comment('お気に入りチームID');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('ユーザーID');
            $table->foreignId('team_id')->constrained()->cascadeOnDelete()->comment('チームID');
            $table->timestamps();
            $table->unique(['user_id', 'team_id']);
        });

        DB::statement("ALTER TABLE favorite_teams COMMENT = 'お気に入りチーム'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_teams');
    }
};
