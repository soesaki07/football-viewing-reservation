<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_index_uses_first_stage_unless_valid_stage_is_given(): void
    {
        $competition = Competition::create(['external_competition_id' => 2000, 'code' => 'WC', 'name' => 'FIFA World Cup', 'area_name' => 'World', 'is_active' => true]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Brazil']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Germany']);
        $group = $this->createMatch($competition, $home, $away, ['match_day' => null, 'stage' => 'GROUP_STAGE']);
        $final = $this->createMatch($competition, $away, $home, ['match_day' => null, 'stage' => 'FINAL']);

        // (a) 指定なしと (b) 配列は、最初のステージ（GROUP_STAGE）の試合だけが出る
        foreach (['/matches?competition=WC', '/matches?competition=WC&stage[]=FINAL'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertSame('GROUP_STAGE', $response->viewData('stage'), $url);
            $this->assertSame([$group->id], $response->viewData('footballMatches')->pluck('id')->all(), $url);
        }

        // (c) 文字列で指定したステージはそのまま使う
        $response = $this->get('/matches?competition=WC&stage=FINAL');
        $this->assertSame('FINAL', $response->viewData('stage'));
        $this->assertSame([$final->id], $response->viewData('footballMatches')->pluck('id')->all());
    }

    public function test_index_treats_invalid_utf8_and_blank_values_as_defaults(): void
    {
        $pl = Competition::create(['external_competition_id' => 2021, 'code' => 'PL', 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true]);
        $wc = Competition::create(['external_competition_id' => 2000, 'code' => 'WC', 'name' => 'FIFA World Cup', 'area_name' => 'World', 'is_active' => true]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $plMatch = $this->createMatch($pl, $home, $away, ['match_day' => 1]);
        $group = $this->createMatch($wc, $home, $away, ['match_day' => null, 'stage' => 'GROUP_STAGE']);
        $this->createMatch($wc, $away, $home, ['match_day' => null, 'stage' => 'FINAL']);

        // UTF-8 として正しくない competition は指定なし（PL の第1節）
        $response = $this->get('/matches?competition=%FF');
        $response->assertOk();
        $this->assertSame('PL', $response->viewData('competitionCode'));
        $this->assertSame([$plMatch->id], $response->viewData('footballMatches')->pluck('id')->all());

        // UTF-8 として正しくない stage、空白だけの stage は、最初のステージ
        foreach (['%FF', '%20', '+'] as $stage) {
            $response = $this->get('/matches?competition=WC&stage='.$stage);
            $response->assertOk();
            $this->assertSame('GROUP_STAGE', $response->viewData('stage'), $stage);
            $this->assertSame([$group->id], $response->viewData('footballMatches')->pluck('id')->all(), $stage);
        }

        // 検索モードで competition が不正なバイト列でも 500 にならず、エラー表示になる
        $this->get('/matches?team=Arsenal&competition=%FF')
            ->assertOk()
            ->assertSee('選択した大会は存在しません。')
            ->assertDontSee('検索結果');
    }

    public function test_index_with_empty_match_day_shows_first_matchday(): void
    {
        $competition = Competition::create(['external_competition_id' => 2021, 'code' => 'PL', 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $first = $this->createMatch($competition, $home, $away, ['match_day' => 1]);
        $second = $this->createMatch($competition, $away, $home, ['match_day' => 2]);

        // match_day が空のときは第1節として扱う（現在の振る舞い）
        $response = $this->get('/matches?match_day=');

        $response->assertOk();
        $this->assertSame([$first->id], $response->viewData('footballMatches')->pluck('id')->all());
        $this->assertNotContains($second->id, $response->viewData('footballMatches')->pluck('id')->all());
    }

    public function test_index_treats_array_parameters_as_defaults(): void
    {
        $competition = Competition::create(['external_competition_id' => 2021, 'code' => 'PL', 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true]);
        $home = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $away = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $match = $this->createMatch($competition, $home, $away, ['match_day' => 1]);
        $secondDay = $this->createMatch($competition, $away, $home, ['match_day' => 2]);

        foreach (['competition[]=CL', 'match_day[]=2', 'stage[]=x', 'competition[a]=b&match_day[a]=2&stage[a]=x'] as $query) {
            $response = $this->get('/matches?'.$query);
            $response->assertOk();
            // 既定（PL・第1節）の表示になる
            $this->assertSame('PL', $response->viewData('competitionCode'), $query);
            $this->assertSame(1, $response->viewData('matchDay'), $query);
            $this->assertSame([$match->id], $response->viewData('footballMatches')->pluck('id')->all(), $query);
            $this->assertNotContains($secondDay->id, $response->viewData('footballMatches')->pluck('id')->all());
        }
    }

    public function test_index_shows_favorite_teams_by_default_when_user_has_favorites(): void
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
        $this->createMatch($competition, $home, $away, ['kickoff_at' => now()->addDay()]);

        $user = User::factory()->create();
        $user->favoriteTeams()->sync([$home->id]);
        $this->actingAs($user);

        $response = $this->get('/matches');

        $response->assertOk();
        $response->assertSee('Arsenal FC');
        $response->assertSee('消化試合');
        $response->assertSee('これからの試合');
        $response->assertDontSee('何節か選択');
    }

    public function test_index_shows_full_list_when_favorite_param_is_zero(): void
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

        $user = User::factory()->create();
        $user->favoriteTeams()->sync([$home->id]);
        $this->actingAs($user);

        $response = $this->get('/matches?favorite=0');

        $response->assertOk();
        $response->assertSee('何節か選択');
    }

    public function test_index_shows_separate_section_per_favorite_team(): void
    {
        $competition = Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);
        $teamA = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $teamB = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
        $other = Team::create(['external_team_id' => 3, 'name' => 'Liverpool FC']);
        $this->createMatch($competition, $teamA, $other, ['kickoff_at' => now()->addDay()]);
        $this->createMatch($competition, $teamB, $other, ['kickoff_at' => now()->addDays(2), 'external_match_id' => 999996]);

        $user = User::factory()->create();
        $user->favoriteTeams()->sync([$teamA->id, $teamB->id]);
        $this->actingAs($user);

        $response = $this->get('/matches');

        $response->assertOk();
        $response->assertSeeInOrder(['Arsenal FC', 'Chelsea FC']);
    }

    public function test_index_ignores_favorite_param_when_user_has_no_favorites(): void
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

        $response = $this->get('/matches?favorite=1');

        $response->assertOk();
        $response->assertSee('何節か選択');
    }

    // ---- 放映店舗一覧 ----

    /**
     * 現在時刻を固定し、まだ始まっていない試合を作る。
     */
    private function createUpcomingMatch(array $overrides = []): FootballMatch
    {
        // 2026-10-25 12:00 UTC を「今」とする
        $this->travelTo('2026-10-25 12:00:00');

        $competition = Competition::firstOrCreate(
            ['code' => 'PL'],
            ['external_competition_id' => 2021, 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true],
        );
        $home = Team::firstOrCreate(['external_team_id' => 1], ['name' => 'Arsenal FC']);
        $away = Team::firstOrCreate(['external_team_id' => 2], ['name' => 'Chelsea FC']);

        return $this->createMatch($competition, $home, $away, array_merge([
            'kickoff_at' => '2026-10-26 20:00:00',
            'status' => 'TIMED',
            'home_score' => null,
            'away_score' => null,
        ], $overrides));
    }

    private function createShopFor(FootballMatch $match, string $name, array $shopOverrides = [], array $broadcastOverrides = []): Broadcast
    {
        $shop = Shop::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'status' => Shop::STATUS_PUBLISHED,
        ], $shopOverrides));

        return Broadcast::factory()->forMatch($match)->create(array_merge([
            'shop_id' => $shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
        ], $broadcastOverrides));
    }

    private function addSeatType(Broadcast $broadcast, int $capacity, int $price, array $broadcastSeatOverrides = [], bool $seatTypeActive = true): BroadcastSeatType
    {
        $seatType = SeatType::factory()->create([
            'shop_id' => $broadcast->shop_id,
            'name' => '席'.SeatType::count(),
            'default_capacity' => $capacity,
            'default_price' => $price,
            'is_active' => $seatTypeActive,
        ]);

        return BroadcastSeatType::factory()->forSeatType($seatType)->create(array_merge([
            'broadcast_id' => $broadcast->id,
        ], $broadcastSeatOverrides));
    }

    private function addReservation(BroadcastSeatType $seatType, int $people, string $status = 'confirmed'): Reservation
    {
        static $code = 0;
        $code++;

        // user_id・金額・状態などは一括代入できないため、unguarded で明示して作る
        return Reservation::unguarded(fn () => Reservation::create([
            'reservation_code' => 'TEST'.$code,
            'user_id' => User::factory()->create()->id,
            'broadcast_seat_type_id' => $seatType->id,
            'number_of_people' => $people,
            'unit_price' => $seatType->price,
            'total_price' => $seatType->price * $people,
            'status' => $status,
            'reserved_at' => now(),
        ]));
    }

    public function test_show_displays_broadcast_shop_with_price_and_seats(): void
    {
        $match = $this->createUpcomingMatch();
        $broadcast = $this->createShopFor($match, 'テストバー');
        $this->addSeatType($broadcast, 10, 4000);
        $this->addSeatType($broadcast, 5, 3000);

        $response = $this->get(route('matches.show', $match->id));

        $response->assertOk();
        $response->assertSee('href="'.route('shops.show', $broadcast->shop_id).'"', false);
        $response->assertSee('テストバー');
        $response->assertSee('東京都渋谷区');
        $response->assertSee('3,000円〜');
        $response->assertDontSee('4,000円〜');
        $this->assertRemainingSeats($response, 15);
    }

    public function test_show_counts_only_confirmed_reservations(): void
    {
        $match = $this->createUpcomingMatch();
        $broadcast = $this->createShopFor($match, 'テストバー');
        $seatType = $this->addSeatType($broadcast, 20, 3000);
        $this->addReservation($seatType, 2);
        $this->addReservation($seatType, 3);
        $this->addReservation($seatType, 4, 'cancelled');
        $this->addReservation($seatType, 4, 'no_show');
        $this->addReservation($seatType, 4, 'visited');

        $this->assertRemainingSeats($this->get(route('matches.show', $match->id)), 15);
    }

    public function test_show_does_not_return_negative_seats_for_overbooked_seat_type(): void
    {
        $match = $this->createUpcomingMatch();
        $broadcast = $this->createShopFor($match, 'テストバー');
        $overbooked = $this->addSeatType($broadcast, 2, 3000);
        $this->addSeatType($broadcast, 5, 3500);
        $this->addReservation($overbooked, 6);

        // 定員超過の座席種別は 0 席として数え、他の種別の 5 席を打ち消さない
        $this->assertRemainingSeats($this->get(route('matches.show', $match->id)), 5);
    }

    public function test_show_displays_full_when_no_seats_left(): void
    {
        $match = $this->createUpcomingMatch();
        $broadcast = $this->createShopFor($match, 'テストバー');
        $seatType = $this->addSeatType($broadcast, 2, 3000);
        $this->addReservation($seatType, 2);

        $response = $this->get(route('matches.show', $match->id));

        $response->assertSee('満席');
        $response->assertSee('3,000円〜');
        $response->assertDontSee('残り0席');
    }

    public function test_show_hides_excluded_broadcasts(): void
    {
        $match = $this->createUpcomingMatch();
        $otherMatch = $this->createMatch(
            Competition::firstOrFail(), Team::where('external_team_id', 1)->firstOrFail(), Team::where('external_team_id', 2)->firstOrFail(),
            ['kickoff_at' => '2026-10-27 20:00:00', 'status' => 'TIMED'],
        );

        $visible = $this->createShopFor($match, '表示される店');
        $this->addSeatType($visible, 5, 3000);

        $draftShop = $this->createShopFor($match, '非公開の店', ['status' => Shop::STATUS_DRAFT]);
        $this->addSeatType($draftShop, 5, 3000);

        $deletedShop = $this->createShopFor($match, '削除済みの店');
        $this->addSeatType($deletedShop, 5, 3000);
        $deletedShop->shop->delete();

        $draftBroadcast = $this->createShopFor($match, '非公開放映の店', [], ['status' => Broadcast::STATUS_DRAFT]);
        $this->addSeatType($draftBroadcast, 5, 3000);

        $inactiveBroadcastSeat = $this->createShopFor($match, '放映座席無効の店');
        $this->addSeatType($inactiveBroadcastSeat, 5, 3000, ['is_active' => false]);

        $inactiveSeatType = $this->createShopFor($match, '座席種別無効の店');
        $this->addSeatType($inactiveSeatType, 5, 3000, [], false);

        $this->createShopFor($match, '座席種別なしの店');

        $otherMatchShop = $this->createShopFor($otherMatch, '別試合の店');
        $this->addSeatType($otherMatchShop, 5, 3000);

        $response = $this->get(route('matches.show', $match->id));

        $response->assertSee('表示される店');
        foreach (['非公開の店', '削除済みの店', '非公開放映の店', '放映座席無効の店', '座席種別無効の店', '座席種別なしの店', '別試合の店'] as $name) {
            $response->assertDontSee($name);
        }
    }

    public function test_show_ignores_inactive_seat_types_in_price_and_seats(): void
    {
        $match = $this->createUpcomingMatch();
        $broadcast = $this->createShopFor($match, 'テストバー');
        $this->addSeatType($broadcast, 5, 4000);
        $this->addSeatType($broadcast, 10, 1000, ['is_active' => false]);
        $this->addSeatType($broadcast, 10, 500, [], false);

        $response = $this->get(route('matches.show', $match->id));

        $response->assertSee('4,000円〜');
        $this->assertRemainingSeats($response, 5);
    }

    /**
     * 「残りN席」が要素の中身そのものとして出ていることを確かめる（「残り25席」の一部に一致して通らないように）。
     */
    private function assertRemainingSeats(TestResponse $response, int $seats): void
    {
        $this->assertMatchesRegularExpression('/>\s*残り'.$seats.'席\s*</', $response->getContent());
    }

    /**
     * 並び順のリンクを、表示名をキーに「href のクエリ」と「選択中か」にして返す。
     *
     * @return array<string, array{query: array<string, mixed>, current: bool}>
     */
    private function sortLinks(TestResponse $response): array
    {
        preg_match_all('#<a href="([^"]+)"([^>]*)>(空席の多い順|料金の安い順|店舗名順)</a>#', $response->getContent(), $found, PREG_SET_ORDER);

        $links = [];
        foreach ($found as $link) {
            parse_str((string) parse_url(html_entity_decode($link[1]), PHP_URL_QUERY), $query);
            $links[$link[3]] = ['query' => $query, 'current' => str_contains($link[2], 'aria-current="true"')];
        }

        return $links;
    }

    /** 画面に出た店舗のリンク先の ID を並び順どおりに返す */
    private function listedShopIds(TestResponse $response): array
    {
        preg_match_all('#/shops/(\d+)"#', $response->getContent(), $found);

        return array_map('intval', $found[1]);
    }

    private function createSortFixture(): FootballMatch
    {
        $match = $this->createUpcomingMatch();
        // 席数：B=3、A=10、C=10（A と C は同数）。料金：A=3000、B=1000、C=2000
        $a = $this->createShopFor($match, 'Aバー');
        $this->addSeatType($a, 10, 3000);
        $b = $this->createShopFor($match, 'Bバー');
        $this->addSeatType($b, 3, 1000);
        $c = $this->createShopFor($match, 'Cバー');
        $this->addSeatType($c, 10, 2000);

        return $match;
    }

    public function test_show_sorts_by_seats_by_default(): void
    {
        $match = $this->createSortFixture();

        $this->get(route('matches.show', $match->id))->assertSeeInOrder(['Aバー', 'Cバー', 'Bバー']);
    }

    public function test_show_sorts_by_price(): void
    {
        $match = $this->createSortFixture();

        $this->get(route('matches.show', ['id' => $match->id, 'sort' => 'price']))
            ->assertSeeInOrder(['Bバー', 'Cバー', 'Aバー']);
    }

    public function test_show_sorts_by_name(): void
    {
        $match = $this->createSortFixture();

        $this->get(route('matches.show', ['id' => $match->id, 'sort' => 'name']))
            ->assertSeeInOrder(['Aバー', 'Bバー', 'Cバー']);
    }

    public function test_show_treats_invalid_sort_as_seats(): void
    {
        $match = $this->createSortFixture();

        $this->get(route('matches.show', ['id' => $match->id, 'sort' => "price' OR 1=1"]))
            ->assertOk()
            ->assertSeeInOrder(['Aバー', 'Cバー', 'Bバー']);
    }

    public function test_show_sort_links_keep_existing_query_parameters(): void
    {
        $match = $this->createSortFixture();

        $response = $this->get(route('matches.show', ['id' => $match->id, 'favorite' => 0, 'sort' => 'name']));

        // sort と favorite が同じ href に入っている
        $links = $this->sortLinks($response);
        $this->assertSame(['空席の多い順', '料金の安い順', '店舗名順'], array_keys($links));
        $this->assertSame(['favorite' => '0', 'sort' => 'seats'], $links['空席の多い順']['query']);
        $this->assertSame(['favorite' => '0', 'sort' => 'price'], $links['料金の安い順']['query']);
        $this->assertSame(['favorite' => '0', 'sort' => 'name'], $links['店舗名順']['query']);
    }

    public function test_show_marks_only_selected_sort_link_as_current(): void
    {
        $match = $this->createSortFixture();

        $expected = [
            'seats' => '空席の多い順',
            'price' => '料金の安い順',
            'name' => '店舗名順',
        ];
        foreach ($expected as $sort => $label) {
            $links = $this->sortLinks($this->get(route('matches.show', ['id' => $match->id, 'sort' => $sort])));
            $this->assertSame([$label], array_keys(array_filter($links, fn (array $link) => $link['current'])), $sort);
        }

        // 不正な sort と sort なしは、既定（空席の多い順）が選択中
        foreach ([[], ['sort' => 'xxx']] as $query) {
            $links = $this->sortLinks($this->get(route('matches.show', ['id' => $match->id] + $query)));
            $this->assertSame(['空席の多い順'], array_keys(array_filter($links, fn (array $link) => $link['current'])));
        }
    }

    public function test_show_orders_same_name_shops_by_shop_id(): void
    {
        $match = $this->createUpcomingMatch();
        $first = Shop::factory()->create(['user_id' => User::factory()->create()->id, 'name' => '同名バー', 'status' => Shop::STATUS_PUBLISHED]);
        $second = Shop::factory()->create(['user_id' => User::factory()->create()->id, 'name' => '同名バー', 'status' => Shop::STATUS_PUBLISHED]);
        // 放映は店舗 ID と逆の順に作り、放映の取得順に頼らず店舗 ID で並ぶことを確かめる
        foreach ([$second, $first] as $shop) {
            $broadcast = Broadcast::factory()->forMatch($match)->create(['shop_id' => $shop->id, 'status' => Broadcast::STATUS_PUBLISHED]);
            $this->addSeatType($broadcast, 10, 3000);
        }

        foreach (['seats', 'price', 'name'] as $sort) {
            $response = $this->get(route('matches.show', ['id' => $match->id, 'sort' => $sort]));
            $this->assertSame([$first->id, $second->id], $this->listedShopIds($response), $sort);
        }
    }

    public function test_show_breaks_price_ties_by_name_then_shop_id(): void
    {
        $match = $this->createUpcomingMatch();
        // 同じ最低料金 1000 円。席数は変えて、席数順では結果が変わらないようにする
        $b = $this->createShopFor($match, 'Bバー');
        $this->addSeatType($b, 20, 1000);
        $a = $this->createShopFor($match, 'Aバー');
        $this->addSeatType($a, 3, 1000);
        $cheap = $this->createShopFor($match, 'Zバー');
        $this->addSeatType($cheap, 5, 500);

        $response = $this->get(route('matches.show', ['id' => $match->id, 'sort' => 'price']));

        $this->assertSame([$cheap->shop_id, $a->shop_id, $b->shop_id], $this->listedShopIds($response));
    }

    public function test_show_uses_constant_query_count_regardless_of_shop_count(): void
    {
        $match = $this->createUpcomingMatch();
        $first = $this->createShopFor($match, 'Aバー');
        $this->addSeatType($first, 5, 3000);

        // 認証ユーザーのお気に入りチームは最初の1回だけ読み込まれるので、先に1回アクセスしておく
        $this->get(route('matches.show', $match->id))->assertOk();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('matches.show', $match->id))->assertOk();
        $before = count(DB::getQueryLog());

        foreach (['Bバー', 'Cバー', 'Dバー'] as $name) {
            $broadcast = $this->createShopFor($match, $name);
            $this->addSeatType($broadcast, 5, 3000);
            $this->addSeatType($broadcast, 5, 2000);
        }
        DB::flushQueryLog();
        $this->get(route('matches.show', $match->id))->assertOk();
        $after = count(DB::getQueryLog());

        $this->assertSame($before, $after);
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string|null}>
     */
    public static function reservationPeriodProvider(): array
    {
        // 「今」は 2026-10-25 12:00 UTC
        return [
            '開始前' => ['2026-10-26 00:00:00', '2026-10-27 00:00:00', '受付前'],
            '終了後' => ['2026-10-24 00:00:00', '2026-10-25 11:59:00', '受付終了'],
            '期間内' => ['2026-10-24 00:00:00', '2026-10-26 00:00:00', null],
            '両方 null' => [null, null, null],
            '開始のみ null で終了後' => [null, '2026-10-25 11:00:00', '受付終了'],
            '終了のみ null で開始前' => ['2026-10-26 00:00:00', null, '受付前'],
        ];
    }

    #[DataProvider('reservationPeriodProvider')]
    public function test_show_displays_reservation_period_badge(?string $startAt, ?string $endAt, ?string $badge): void
    {
        $match = $this->createUpcomingMatch();
        $broadcast = $this->createShopFor($match, 'テストバー', [], [
            'reservation_start_at' => $startAt,
            'reservation_end_at' => $endAt,
        ]);
        $this->addSeatType($broadcast, 5, 3000);

        $response = $this->get(route('matches.show', $match->id));

        // 受付期間外でも一覧からは外さない
        $response->assertSee('テストバー');
        foreach (['受付前', '受付終了'] as $label) {
            $label === $badge ? $response->assertSee($label) : $response->assertDontSee($label);
        }
    }

    public function test_show_displays_message_when_no_broadcast_shops(): void
    {
        $match = $this->createUpcomingMatch();

        $this->get(route('matches.show', $match->id))->assertSee('放映店舗がありません。');
    }

    public function test_show_displays_message_when_all_broadcasts_are_excluded(): void
    {
        $match = $this->createUpcomingMatch();
        $this->createShopFor($match, '座席種別なしの店');

        $this->get(route('matches.show', $match->id))->assertSee('放映店舗がありません。');
    }

    #[DataProvider('finishedStatusProvider')]
    public function test_show_hides_list_for_finished_status(string $status): void
    {
        $match = $this->createUpcomingMatch(['status' => $status]);
        $broadcast = $this->createShopFor($match, 'テストバー');
        $this->addSeatType($broadcast, 5, 3000);

        $response = $this->get(route('matches.show', $match->id));

        $response->assertSee('この試合の放映は終了しました。');
        $response->assertDontSee('テストバー');
        $response->assertDontSee('放映店舗がありません。');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function finishedStatusProvider(): array
    {
        return ['FINISHED' => ['FINISHED'], 'AWARDED' => ['AWARDED'], 'CANCELLED' => ['CANCELLED']];
    }

    public function test_show_hides_list_when_three_hours_passed_since_kickoff(): void
    {
        // status が古いまま（TIMED）でも、キックオフから3時間経過していれば終了扱い
        $match = $this->createUpcomingMatch(['kickoff_at' => '2026-10-25 09:00:00']);
        $broadcast = $this->createShopFor($match, 'テストバー');
        $this->addSeatType($broadcast, 5, 3000);

        $this->get(route('matches.show', $match->id))
            ->assertSee('この試合の放映は終了しました。')
            ->assertDontSee('テストバー');
    }

    public function test_show_displays_list_just_before_three_hours_since_kickoff(): void
    {
        $match = $this->createUpcomingMatch(['kickoff_at' => '2026-10-25 09:01:00']);
        $broadcast = $this->createShopFor($match, 'テストバー');
        $this->addSeatType($broadcast, 5, 3000);

        $this->get(route('matches.show', $match->id))
            ->assertSee('テストバー')
            ->assertDontSee('この試合の放映は終了しました。');
    }
}
