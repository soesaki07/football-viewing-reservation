<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function createMatch(Competition $competition, Team $homeTeam, Team $awayTeam, array $overrides = []): FootballMatch
    {
        static $externalId = 900000;
        $externalId++;

        return FootballMatch::create(array_merge([
            'external_match_id' => $externalId,
            'competition_id' => $competition->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'season_start_year' => 2026,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => '2026-08-20 18:00:00',
            'status' => 'FINISHED',
            'home_score' => 2,
            'away_score' => 1,
            'venue' => 'Test Stadium',
            'last_api_synced_at' => now(),
        ], $overrides));
    }

    public function test_index_shows_default_premier_league_first_matchday(): void
    {
        $competition = Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $this->createMatch($competition, $home, $away, ['match_day' => 1]);
        $this->createMatch($competition, $home, $away, ['match_day' => 2, 'external_match_id' => 999998]);

        $response = $this->get('/matches');

        $response->assertOk();
        $response->assertSee('Arsenal FC');
        $response->assertSee('何節か選択');
    }

    public function test_index_shows_matches_filtered_by_matchday(): void
    {
        $competition = Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $other = Team::create(['external_team_id' => 3, 'name' => 'Liverpool FC']);
        $this->createMatch($competition, $home, $away, ['match_day' => 1]);
        $this->createMatch($competition, $away, $other, ['match_day' => 2, 'external_match_id' => 999997]);

        $response = $this->get('/matches?competition=PL&match_day=2');

        $response->assertOk();
        $response->assertSee('Liverpool FC');
        $response->assertDontSee('Arsenal FC');
    }

    public function test_index_shows_stage_selector_for_tournament_competition(): void
    {
        $competition = Competition::create([
            'external_competition_id' => 2000,
            'code' => 'WC',
            'name' => 'FIFA World Cup',
            'area_name' => 'World',
            'is_active' => true,
        ]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Brazil']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Germany']);
        $this->createMatch($competition, $home, $away, [
            'match_day' => null,
            'stage' => 'FINAL',
        ]);

        $response = $this->get('/matches?competition=WC&stage=FINAL');

        $response->assertOk();
        $response->assertSee('Brazil');
        $response->assertSee('ステージ選択');
        $response->assertDontSee('何節か選択');
    }

    public function test_show_displays_match_details(): void
    {
        $competition = Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $match = $this->createMatch($competition, $home, $away);

        $response = $this->get("/matches/{$match->id}");

        $response->assertOk();
        $response->assertSee('Arsenal FC');
        $response->assertSee('Chelsea FC');
        $response->assertSee('2 - 1');
    }

    public function test_show_returns_404_for_nonexistent_match(): void
    {
        $response = $this->get('/matches/999999');

        $response->assertNotFound();
    }

    public function test_index_shows_empty_state_for_unknown_competition(): void
    {
        $response = $this->get('/matches?competition=DOES_NOT_EXIST');

        $response->assertOk();
        $response->assertSee('該当する試合が見つかりませんでした。');
    }
}
