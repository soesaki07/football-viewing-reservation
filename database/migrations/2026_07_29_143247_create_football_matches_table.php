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
        Schema::create('football_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_match_id')->unique();
            $table->foreignId('competition_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('season_start_year')->nullable()->index();
            $table->unsignedSmallInteger('match_day')->nullable();
            $table->string('stage', 50)->nullable();
            $table->dateTime('kickoff_at')->index();
            $table->string('status', 30)->default('scheduled')->index();
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->string('venue')->nullable();
            $table->timestamp('last_api_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('football_matches');
    }
};
