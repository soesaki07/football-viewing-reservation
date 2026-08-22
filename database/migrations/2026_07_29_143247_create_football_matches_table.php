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
        Schema::create('football_matches', function (Blueprint $table) {
            $table->id()->comment('試合ID');
            $table->unsignedBigInteger('external_match_id')->unique()->comment('外部API側の試合ID');
            $table->foreignId('competition_id')->constrained()->cascadeOnUpdate()->restrictOnDelete()->comment('大会ID');
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnUpdate()->restrictOnDelete()->comment('ホームチームID');
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnUpdate()->restrictOnDelete()->comment('アウェイチームID');
            $table->unsignedSmallInteger('season_start_year')->nullable()->index()->comment('シーズン開始年');
            $table->unsignedSmallInteger('match_day')->nullable()->comment('節');
            $table->string('stage', 50)->nullable()->comment('大会ステージ');
            $table->dateTime('kickoff_at')->index()->comment('キックオフ日時');
            $table->string('status', 30)->default('scheduled')->index()->comment('試合状態');
            $table->unsignedSmallInteger('home_score')->nullable()->comment('ホームチーム得点');
            $table->unsignedSmallInteger('away_score')->nullable()->comment('アウェイチーム得点');
            $table->string('venue')->nullable()->comment('開催スタジアム');
            $table->timestamp('last_api_synced_at')->nullable()->comment('API最終同期日時');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE football_matches COMMENT = '試合情報'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('football_matches');
    }
};
