<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchSearchTest extends TestCase
{
    use RefreshDatabase;

    private Competition $pl;

    private Competition $cl;

    private Team $arsenal;

    private Team $chelsea;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->pl = Competition::create(['external_competition_id' => 2021, 'code' => 'PL', 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true]);
        $this->cl = Competition::create(['external_competition_id' => 2001, 'code' => 'CL', 'name' => 'Champions League', 'area_name' => 'Europe', 'is_active' => true]);
        $this->arsenal = $this->makeTeam('Arsenal FC', 'Arsenal', 'ARS');
        $this->chelsea = $this->makeTeam('Chelsea FC', 'Chelsea', 'CHE');
    }

    private function makeTeam(string $name, string $shortName, string $tla): Team
    {
        return Team::create([
            'external_team_id' => 5000 + (++$this->seq),
            'name' => $name,
            'short_name' => $shortName,
            'tla' => $tla,
        ]);
    }

    private function makeMatch(Team $home, Team $away, string $kickoffUtc, ?Competition $competition = null): FootballMatch
    {
        return FootballMatch::create([
            'external_match_id' => 700000 + (++$this->seq),
            'competition_id' => ($competition ?? $this->pl)->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'season_start_year' => 2026,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => $kickoffUtc,
            'status' => 'SCHEDULED',
            'last_api_synced_at' => now(),
        ]);
    }

    /** 画面に出た試合カードのリンク先の ID を並び順どおりに返す */
    private function listedIds(string $html): array
    {
        preg_match_all('#/matches/(\d+)"#', $html, $found);

        return array_map('intval', $found[1]);
    }

    public function test_date_search_uses_japan_time_boundaries(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        // JST 2026-09-10 は UTC 2026-09-09 15:00:00 〜 2026-09-10 14:59:59
        $before = $this->makeMatch($this->arsenal, $other, '2026-09-09 14:59:59');
        $start = $this->makeMatch($this->arsenal, $other, '2026-09-09 15:00:00');
        $end = $this->makeMatch($this->chelsea, $other, '2026-09-10 14:59:59');
        $after = $this->makeMatch($this->chelsea, $other, '2026-09-10 15:00:00');

        $response = $this->get(route('matches.index', ['date' => '2026-09-10']));

        $response->assertOk()->assertSee('検索結果（2件）', false);
        $this->assertSame([$start->id, $end->id], $this->listedIds($response->getContent()));
        $this->assertNotContains($before->id, $this->listedIds($response->getContent()));
        $this->assertNotContains($after->id, $this->listedIds($response->getContent()));
        $response->assertDontSee('今後の試合');
    }

    public function test_team_search_matches_name_short_name_and_tla_for_home_and_away(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $home = $this->makeMatch($this->arsenal, $other, now()->addDays(2)->toDateTimeString());
        $away = $this->makeMatch($other, $this->arsenal, now()->addDays(3)->toDateTimeString());
        $none = $this->makeMatch($this->chelsea, $other, now()->addDays(4)->toDateTimeString());

        foreach (['Arsenal FC', 'senal', 'ARS', 'ars', 'ARSENAL', '  ars  '] as $keyword) {
            $ids = $this->listedIds($this->get(route('matches.index', ['team' => $keyword]))->getContent());
            $this->assertSame([$home->id, $away->id], $ids, $keyword);
            $this->assertNotContains($none->id, $ids);
        }
    }

    public function test_team_search_matches_japanese_name_and_short_name(): void
    {
        $this->arsenal->update(['name_ja' => 'アーセナルFC', 'short_name_ja' => 'アーセナル']);
        $this->chelsea->update(['short_name_ja' => 'チェルシー']);
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $home = $this->makeMatch($this->arsenal, $other, now()->addDays(2)->toDateTimeString());
        $away = $this->makeMatch($other, $this->arsenal, now()->addDays(3)->toDateTimeString());
        $chelsea = $this->makeMatch($this->chelsea, $other, now()->addDays(4)->toDateTimeString());

        // 日本語の正式名の一部、略称
        foreach (['アーセナ', 'ナルFC', 'アーセナル'] as $keyword) {
            $ids = $this->listedIds($this->get(route('matches.index', ['team' => $keyword]))->getContent());
            $this->assertSame([$home->id, $away->id], $ids, $keyword);
        }

        // 日本語の略称のみ持つチーム、日本語名がないチームは英語名でヒットする
        $this->assertSame([$chelsea->id], $this->listedIds($this->get(route('matches.index', ['team' => 'チェルシー']))->getContent()));
        $this->assertSame([$chelsea->id], $this->listedIds($this->get(route('matches.index', ['team' => 'Chelsea']))->getContent()));
        $this->assertSame([], $this->listedIds($this->get(route('matches.index', ['team' => 'マンチェスター']))->getContent()));
    }

    public function test_like_wildcards_in_team_are_escaped(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $this->makeMatch($this->arsenal, $other, now()->addDay()->toDateTimeString());

        foreach (['%', '_', 'A%l', 'Ar_enal', '!'] as $keyword) {
            $this->get(route('matches.index', ['team' => $keyword]))
                ->assertOk()
                ->assertSee('条件に合う試合はありません。');
        }
    }

    public function test_competition_filter(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $inPl = $this->makeMatch($this->arsenal, $other, now()->addDay()->toDateTimeString());
        $inCl = $this->makeMatch($this->arsenal, $other, now()->addDays(2)->toDateTimeString(), $this->cl);

        $all = $this->get(route('matches.index', ['team' => 'Arsenal', 'competition' => '']));
        $this->assertSame([$inPl->id, $inCl->id], $this->listedIds($all->getContent()));

        $cl = $this->get(route('matches.index', ['team' => 'Arsenal', 'competition' => 'CL']));
        $this->assertSame([$inCl->id], $this->listedIds($cl->getContent()));
    }

    public function test_team_search_splits_upcoming_and_past_tabs(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $past1 = $this->makeMatch($this->arsenal, $other, now()->subDays(10)->toDateTimeString());
        $past2 = $this->makeMatch($this->arsenal, $other, now()->subDays(2)->toDateTimeString());
        $next1 = $this->makeMatch($this->arsenal, $other, now()->addDays(2)->toDateTimeString());
        $next2 = $this->makeMatch($this->arsenal, $other, now()->addDays(9)->toDateTimeString());

        $default = $this->get(route('matches.index', ['team' => 'Arsenal']));
        $default->assertSee('今後の試合')->assertSee('過去の試合');
        $this->assertSame([$next1->id, $next2->id], $this->listedIds($default->getContent()));

        $past = $this->get(route('matches.index', ['team' => 'Arsenal', 'period' => 'past']));
        $this->assertSame([$past2->id, $past1->id], $this->listedIds($past->getContent()));

        // 不正な period は upcoming 扱い
        $invalid = $this->get(route('matches.index', ['team' => 'Arsenal', 'period' => 'xxx']));
        $invalid->assertOk();
        $this->assertSame([$next1->id, $next2->id], $this->listedIds($invalid->getContent()));
    }

    public function test_date_and_team_shows_all_of_that_day_without_tabs(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $late = $this->makeMatch($this->arsenal, $other, '2020-01-10 12:00:00');
        $early = $this->makeMatch($other, $this->arsenal, '2020-01-10 03:00:00');
        $this->makeMatch($this->chelsea, $other, '2020-01-10 05:00:00');

        $response = $this->get(route('matches.index', ['date' => '2020-01-10', 'team' => 'ARS', 'period' => 'upcoming']));

        $this->assertSame([$early->id, $late->id], $this->listedIds($response->getContent()));
        $response->assertDontSee('今後の試合')->assertDontSee('過去の試合');
    }

    public function test_search_shows_only_results_even_with_favorite_teams(): void
    {
        $user = User::factory()->create();
        $user->favoriteTeams()->attach($this->chelsea->id);
        $this->actingAs($user);
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $found = $this->makeMatch($this->arsenal, $other, now()->addDay()->toDateTimeString());
        $favorite = $this->makeMatch($this->chelsea, $other, now()->addDay()->toDateTimeString());

        // 検索なしならお気に入り表示
        $this->get(route('matches.index'))->assertSee('お気に入りチーム');

        $response = $this->get(route('matches.index', ['team' => 'Arsenal']));
        $response->assertOk()->assertDontSee('お気に入りチーム')->assertSee('検索をクリア')->assertSee('name="team"', false);
        $this->assertSame([$found->id], $this->listedIds($response->getContent()));
        $this->assertNotContains($favorite->id, $this->listedIds($response->getContent()));
    }

    public function test_pagination_keeps_search_conditions(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        foreach (range(1, 21) as $i) {
            $this->makeMatch($this->arsenal, $other, now()->addDays($i)->toDateTimeString());
        }

        $first = $this->get(route('matches.index', ['team' => 'Arsenal']));
        $this->assertCount(20, $this->listedIds($first->getContent()));
        $first->assertSee('team=Arsenal', false)->assertSee('page=2', false);

        $second = $this->get(route('matches.index', ['team' => 'Arsenal', 'page' => 2]));
        $this->assertCount(1, $this->listedIds($second->getContent()));
        $second->assertSee('検索結果（21件）', false);
    }

    public function test_huge_or_invalid_page_does_not_cause_500(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $this->makeMatch($this->arsenal, $other, now()->addDay()->toDateTimeString());

        foreach (['9223372036854775807', '99999999999999999999999', '10001', 'abc', '-5', '0', '1e30'] as $page) {
            $this->get(route('matches.index', ['team' => 'Ars', 'page' => $page]))->assertOk();
        }
        $this->get('/matches?team=Ars&page[]=1')->assertOk();
    }

    public function test_no_results_message(): void
    {
        $this->get(route('matches.index', ['team' => 'Nonexistent']))
            ->assertOk()
            ->assertSee('条件に合う試合はありません。');
    }

    public function test_invalid_input_shows_errors_and_does_not_search(): void
    {
        $this->get(route('matches.index', ['date' => '2026-02-31']))
            ->assertOk()->assertSee('日付は')->assertDontSee('検索結果');
        $this->get(route('matches.index', ['date' => 'abc']))->assertOk()->assertSee('日付は');
        $this->get(route('matches.index', ['date' => ['x']]))->assertOk();
        $this->get(route('matches.index', ['team' => str_repeat('a', 51)]))
            ->assertOk()->assertSee('チーム名は50文字以内')->assertDontSee('検索結果');
        $this->get(route('matches.index', ['team' => ['x']]))->assertOk();
        $this->get(route('matches.index', ['team' => 'Arsenal', 'competition' => 'ZZZ']))
            ->assertOk()->assertSee('選択した大会は存在しません。')->assertDontSee('検索結果');
    }

    public function test_user_input_is_escaped_in_form(): void
    {
        $this->get(route('matches.index', ['team' => '"><script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_without_search_existing_display_is_kept(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        $match = $this->makeMatch($this->arsenal, $other, now()->addDay()->toDateTimeString());

        $response = $this->get(route('matches.index'));

        $response->assertOk()->assertSee('何節か選択')->assertDontSee('検索結果');
        $this->assertSame([$match->id], $this->listedIds($response->getContent()));
        // 検索フォームの空の大会指定でも、既存の表示（PL）になる
        $this->get(route('matches.index', ['competition' => '']))->assertOk()->assertSee('何節か選択');
    }

    public function test_search_does_not_cause_n_plus_one(): void
    {
        $other = $this->makeTeam('Other FC', 'Other', 'OTH');
        foreach (range(1, 3) as $i) {
            $this->makeMatch($this->arsenal, $this->makeTeam("T$i FC", "T$i", "T$i"), now()->addDays($i)->toDateTimeString());
        }
        // ユーザー関連のキャッシュを揃えるため、先に1回アクセスしてから数える
        $this->get(route('matches.index', ['team' => 'Arsenal']))->assertOk();
        DB::enableQueryLog();
        $this->get(route('matches.index', ['team' => 'Arsenal']))->assertOk();
        $few = count(DB::getQueryLog());

        foreach (range(4, 15) as $i) {
            $this->makeMatch($this->arsenal, $other, now()->addDays($i)->toDateTimeString());
        }
        DB::flushQueryLog();
        $this->get(route('matches.index', ['team' => 'Arsenal']))->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()));
    }
}
