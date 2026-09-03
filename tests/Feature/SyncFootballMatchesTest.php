<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncFootballMatchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.football_data.base_url' => 'https://api.football-data.org/v4',
            'services.football_data.api_key' => 'test-api-key',
        ]);
    }

    private function fakeMatch(int $id, int $homeTeamId, ?int $awayTeamId = 10): array
    {
        return [
            'id' => $id,
            'utcDate' => '2026-08-28T18:30:00Z',
            'status' => 'FINISHED',
            'matchday' => 1,
            'stage' => 'REGULAR_SEASON',
            'competition' => ['id' => 2002, 'name' => 'Bundesliga', 'code' => 'BL1'],
            'season' => ['startDate' => '2026-08-28', 'endDate' => '2027-05-22'],
            'homeTeam' => ['id' => $homeTeamId, 'name' => 'FC Bayern München'],
            'awayTeam' => ['id' => $awayTeamId, 'name' => 'VfB Stuttgart'],
            'score' => ['fullTime' => ['home' => 5, 'away' => 1]],
        ];
    }

    public function test_match_is_synced_when_competition_and_teams_are_already_mapped(): void
    {
        Competition::create([
            'external_competition_id' => 2002,
            'code' => 'BL1',
            'name' => 'Bundesliga',
            'area_name' => 'Germany',
            'is_active' => true,
        ]);
        Team::create(['external_team_id' => 5, 'name' => 'FC Bayern München']);
        Team::create(['external_team_id' => 10, 'name' => 'VfB Stuttgart']);

        Http::fake([
            '*/competitions/BL1/matches' => Http::response([
                'matches' => [$this->fakeMatch(565776, 5, 10)],
            ]),
        ]);

        $this->artisan('app:sync-football-matches')->assertSuccessful();

        $this->assertDatabaseCount('football_matches', 1);
        $this->assertDatabaseHas('football_matches', [
            'external_match_id' => 565776,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => '2026-08-28 18:30:00',
            'status' => 'FINISHED',
            'home_score' => 5,
            'away_score' => 1,
            'season_start_year' => 2026,
        ]);
    }

    public function test_match_is_skipped_when_away_team_is_not_yet_synced(): void
    {
        Competition::create([
            'external_competition_id' => 2002,
            'code' => 'BL1',
            'name' => 'Bundesliga',
            'area_name' => 'Germany',
            'is_active' => true,
        ]);
        Team::create(['external_team_id' => 5, 'name' => 'FC Bayern München']);
        // external_team_id 10 (アウェイチーム) はまだ同期されていない想定

        Http::fake([
            '*/competitions/BL1/matches' => Http::response([
                'matches' => [$this->fakeMatch(565776, 5, 10)],
            ]),
        ]);

        $this->artisan('app:sync-football-matches')->assertSuccessful();

        $this->assertDatabaseCount('football_matches', 0);
    }

    public function test_sync_succeeds_with_no_matches_saved_when_connection_fails(): void
    {
        Competition::create([
            'external_competition_id' => 2002,
            'code' => 'BL1',
            'name' => 'Bundesliga',
            'area_name' => 'Germany',
            'is_active' => true,
        ]);

        Http::fake([
            '*/competitions/BL1/matches' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->artisan('app:sync-football-matches')->assertSuccessful();

        $this->assertDatabaseCount('football_matches', 0);
    }
}
