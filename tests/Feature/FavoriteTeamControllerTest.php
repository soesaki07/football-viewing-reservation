<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTeamControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createMatch(Competition $competition, Team $homeTeam, Team $awayTeam): FootballMatch
    {
        static $externalId = 800000;
        $externalId++;

        return FootballMatch::create([
            'external_match_id' => $externalId,
            'competition_id' => $competition->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'season_start_year' => 2026,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => '2026-08-20 18:00:00',
            'status' => 'SCHEDULED',
        ]);
    }

    public function test_select_favorite_teams_shows_teams_grouped_by_competition(): void
    {
        $user = User::factory()->create();
        $competition = Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $this->createMatch($competition, $home, $away);

        $response = $this->actingAs($user)->get('/select/teams');

        $response->assertOk();
        $response->assertSee('Arsenal FC');
        $response->assertSee('Chelsea FC');
    }

    public function test_guest_cannot_access_select_favorite_teams(): void
    {
        $response = $this->get('/select/teams');

        $response->assertRedirect(route('login'));
    }

    public function test_save_favorite_teams_stores_selected_teams(): void
    {
        $user = User::factory()->create();
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);

        $response = $this->actingAs($user)->put('/select/teams/save', [
            'team_ids' => [$home->id, $away->id],
        ]);

        $response->assertRedirect(route('matches.index'));
        $this->assertSame(2, $user->favoriteTeams()->count());
    }

    public function test_save_favorite_teams_with_no_selection_clears_favorites(): void
    {
        $user = User::factory()->create();
        $team = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $user->favoriteTeams()->sync([$team->id]);

        $response = $this->actingAs($user)->put('/select/teams/save', []);

        $response->assertRedirect(route('matches.index'));
        $this->assertSame(0, $user->favoriteTeams()->count());
    }

    public function test_save_favorite_teams_fails_when_exceeding_max_three(): void
    {
        $user = User::factory()->create();
        $teamIds = [];
        for ($i = 1; $i <= 4; $i++) {
            $teamIds[] = Team::create(['external_team_id' => $i, 'name' => "Team {$i}"])->id;
        }

        $response = $this->actingAs($user)->put('/select/teams/save', [
            'team_ids' => $teamIds,
        ]);

        $response->assertSessionHasErrors('team_ids');
        $this->assertSame(0, $user->favoriteTeams()->count());
    }

    public function test_save_favorite_teams_fails_for_nonexistent_team_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/select/teams/save', [
            'team_ids' => [999999],
        ]);

        $response->assertSessionHasErrors('team_ids.0');
        $this->assertSame(0, $user->favoriteTeams()->count());
    }
}
