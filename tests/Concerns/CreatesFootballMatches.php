<?php

namespace Tests\Concerns;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;

trait CreatesFootballMatches
{
    /**
     * テスト用の試合を作る。キックオフ時刻の既定値は30日後（UTC）。
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createFootballMatch(array $overrides = []): FootballMatch
    {
        static $sequence = 0;
        $sequence++;

        $competition = Competition::firstOrCreate(
            ['external_competition_id' => 2021],
            ['code' => 'PL', 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true],
        );
        $home = Team::firstOrCreate(['external_team_id' => 1], ['name' => 'Arsenal FC']);
        $away = Team::firstOrCreate(['external_team_id' => 2], ['name' => 'Chelsea FC']);

        return FootballMatch::create(array_merge([
            'external_match_id' => 800000 + $sequence,
            'competition_id' => $competition->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'season_start_year' => 2026,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => now()->addDays(30)->startOfMinute(),
            'status' => 'TIMED',
            'home_score' => null,
            'away_score' => null,
            'venue' => 'Test Stadium',
            'last_api_synced_at' => now(),
        ], $overrides));
    }
}
