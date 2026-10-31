<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JapaneseNameOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompetition(int $externalId, string $code, string $name, ?string $nameJa): Competition
    {
        return Competition::create([
            'external_competition_id' => $externalId,
            'code' => $code,
            'name' => $name,
            'name_ja' => $nameJa,
            'area_name' => 'Europe',
            'is_active' => true,
        ]);
    }

    /**
     * 英語名の順と日本語名の順が食い違うように作る（英語名だけなら Aaa が先頭になる）。
     *
     * @return array{Team, Team, Team} 日本語名の順（英語名のみ → ア → ウ）
     */
    private function makeTeams(): array
    {
        $ja2 = Team::create(['external_team_id' => 1, 'name' => 'Aaa FC', 'name_ja' => 'ウォルバーハンプトン']);
        $en = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $ja1 = Team::create(['external_team_id' => 3, 'name' => 'Zzz FC', 'name_ja' => 'アーセナル']);

        return [$en, $ja1, $ja2];
    }

    public function test_match_search_competition_dropdown_is_ordered_by_japanese_name(): void
    {
        $ja2 = $this->makeCompetition(1, 'AAA', 'Aaa League', 'ウ大会');
        $en = $this->makeCompetition(2, 'CCC', 'Champions League', null);
        $ja1 = $this->makeCompetition(3, 'ZZZ', 'Zzz League', 'ア大会');

        $response = $this->actingAs(User::factory()->create())->get(route('matches.index'));

        $response->assertOk();
        $this->assertSame([$en->id, $ja1->id, $ja2->id], $response->viewData('competitions')->pluck('id')->all());
    }

    public function test_favorite_team_page_orders_teams_and_competitions_by_japanese_name(): void
    {
        $competition = $this->makeCompetition(1, 'PL', 'Premier League', null);
        $competitionJa2 = $this->makeCompetition(2, 'AAA', 'Aaa League', 'ウ大会');
        $competitionJa1 = $this->makeCompetition(3, 'ZZZ', 'Zzz League', 'ア大会');
        [$en, $ja1, $ja2] = $this->makeTeams();
        $other = Team::create(['external_team_id' => 9, 'name' => 'Other FC']);
        foreach ([$en, $ja1, $ja2] as $team) {
            FootballMatch::create([
                'external_match_id' => 900000 + $team->id,
                'competition_id' => $competition->id,
                'home_team_id' => $team->id,
                'away_team_id' => $other->id,
                'season_start_year' => 2026,
                'match_day' => 1,
                'stage' => 'REGULAR_SEASON',
                'kickoff_at' => '2026-08-20 18:00:00',
                'status' => 'SCHEDULED',
            ]);
        }

        $response = $this->actingAs(User::factory()->create())->get(route('select.teams'));

        $response->assertOk();
        $this->assertSame([$competition->id, $competitionJa1->id, $competitionJa2->id], $response->viewData('competitions')->pluck('id')->all());
        $this->assertSame([$en->id, $other->id, $ja1->id, $ja2->id], $response->viewData('teams')->pluck('id')->all());
    }

    public function test_mypage_favorite_teams_are_ordered_by_japanese_name(): void
    {
        $user = User::factory()->create();
        [$en, $ja1, $ja2] = $this->makeTeams();
        $user->favoriteTeams()->sync([$ja2->id, $en->id, $ja1->id]);

        $response = $this->actingAs($user)->get(route('mypage'));

        $response->assertOk();
        $this->assertSame([$en->id, $ja1->id, $ja2->id], $response->viewData('favoriteTeams')->pluck('id')->all());
    }
}
