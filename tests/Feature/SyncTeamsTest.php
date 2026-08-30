<?php

namespace Tests\Feature;

use App\Models\Competition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncTeamsTest extends TestCase
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

    private function fakeTeamsResponse(string $competitionCode, array $teams): void
    {
        Http::fake([
            "*/competitions/{$competitionCode}/teams" => Http::response([
                'teams' => $teams,
            ]),
        ]);
    }

    public function test_teams_from_multiple_competitions_are_synced(): void
    {
        Competition::create([
            'external_competition_id' => 2002,
            'code' => 'BL1',
            'name' => 'Bundesliga',
            'area_name' => 'Germany',
            'is_active' => true,
        ]);

        Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);

        Http::fake([
            '*/competitions/BL1/teams' => Http::response([
                'teams' => [
                    [
                        'id' => 1,
                        'name' => '1. FC Köln',
                        'shortName' => 'Köln',
                        'tla' => 'KOE',
                        'crest' => 'https://crests.football-data.org/1.png',
                        'area' => ['name' => 'Germany'],
                    ],
                ],
            ]),
            '*/competitions/PL/teams' => Http::response([
                'teams' => [
                    [
                        'id' => 57,
                        'name' => 'Arsenal FC',
                        'shortName' => 'Arsenal',
                        'tla' => 'ARS',
                        'crest' => 'https://crests.football-data.org/57.png',
                        'area' => ['name' => 'England'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('app:sync-teams')->assertSuccessful();

        $this->assertDatabaseCount('teams', 2);
        $this->assertDatabaseHas('teams', [
            'external_team_id' => 1,
            'name' => '1. FC Köln',
            'short_name' => 'Köln',
            'tla' => 'KOE',
            'crest_url' => 'https://crests.football-data.org/1.png',
            'country_name' => 'Germany',
        ]);
        $this->assertDatabaseHas('teams', [
            'external_team_id' => 57,
            'name' => 'Arsenal FC',
            'short_name' => 'Arsenal',
            'tla' => 'ARS',
            'crest_url' => 'https://crests.football-data.org/57.png',
            'country_name' => 'England',
        ]);
    }

    public function test_sync_keeps_teams_collected_before_a_malformed_response_is_hit(): void
    {
        Competition::create([
            'external_competition_id' => 2002,
            'code' => 'BL1',
            'name' => 'Bundesliga',
            'area_name' => 'Germany',
            'is_active' => true,
        ]);

        Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);

        Http::fake([
            '*/competitions/BL1/teams' => Http::response([
                'teams' => [
                    [
                        'id' => 1,
                        'name' => '1. FC Köln',
                        'shortName' => 'Köln',
                        'tla' => 'KOE',
                        'crest' => 'https://crests.football-data.org/1.png',
                        'area' => ['name' => 'Germany'],
                    ],
                ],
            ]),
            // teamsキーを含まない、想定外の形のレスポンス
            '*/competitions/PL/teams' => Http::response(['count' => 0]),
        ]);

        $this->artisan('app:sync-teams')->assertSuccessful();

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseHas('teams', [
            'external_team_id' => 1,
            'name' => '1. FC Köln',
        ]);
    }

    public function test_sync_succeeds_with_no_teams_saved_when_connection_fails(): void
    {
        Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);

        Http::fake([
            '*/competitions/PL/teams' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->artisan('app:sync-teams')->assertSuccessful();

        $this->assertDatabaseCount('teams', 0);
    }
}
