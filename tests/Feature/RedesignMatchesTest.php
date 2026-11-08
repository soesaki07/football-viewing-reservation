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
use Tests\TestCase;

/**
 * 画面刷新 PR2（試合まわり）：試合カード・試合詳細の放映店舗の行・店舗詳細・お気に入り登録・ページ送り
 */
class RedesignMatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function createCompetition(): Competition
    {
        return Competition::firstOrCreate(
            ['code' => 'PL'],
            ['external_competition_id' => 2021, 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true],
        );
    }

    private function createMatch(array $overrides = []): FootballMatch
    {
        static $externalId = 800000;
        $externalId++;

        $home = Team::firstOrCreate(['external_team_id' => 1], ['name' => 'Arsenal FC']);
        $away = Team::firstOrCreate(['external_team_id' => 2], ['name' => 'Chelsea FC']);

        return FootballMatch::create(array_merge([
            'external_match_id' => $externalId,
            'competition_id' => $this->createCompetition()->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'season_start_year' => 2026,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => '2026-10-26 20:00:00',
            'status' => 'TIMED',
            'home_score' => null,
            'away_score' => null,
            'last_api_synced_at' => now(),
        ], $overrides));
    }

    /** 放映（公開）と座席種別を1つ持つ店舗を作る */
    private function createBroadcast(FootballMatch $match, string $name, int $capacity = 5, array $broadcastOverrides = []): BroadcastSeatType
    {
        $shop = Shop::factory()->create([
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'status' => Shop::STATUS_PUBLISHED,
        ]);
        $broadcast = Broadcast::factory()->forMatch($match)->create(array_merge([
            'shop_id' => $shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
        ], $broadcastOverrides));
        $seatType = SeatType::factory()->create([
            'shop_id' => $shop->id,
            'name' => '席',
            'default_capacity' => $capacity,
            'default_price' => 3000,
            'is_active' => true,
        ]);

        return BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);
    }

    // ---- 試合一覧・試合カード ----

    public function test_index_card_shows_score_for_finished_and_vs_for_upcoming(): void
    {
        $this->travelTo('2026-10-25 12:00:00');
        $this->createMatch(['match_day' => 1, 'status' => 'FINISHED', 'home_score' => 2, 'away_score' => 1, 'kickoff_at' => '2026-10-20 20:00:00']);
        $this->createMatch(['match_day' => 2]);

        $finished = $this->get(route('matches.index', ['competition' => 'PL', 'match_day' => 1]));
        $finished->assertOk()->assertSee('data-testid="match-score"', false)->assertSee('2 - 1')
            ->assertDontSee('data-testid="match-vs"', false);

        $this->assertMatchesRegularExpression('#<span[^>]*bg-surface-container-high[^>]*>\s*終了\s*</span>#', $finished->getContent());

        $upcoming = $this->get(route('matches.index', ['competition' => 'PL', 'match_day' => 2]));
        $upcoming->assertOk()->assertSee('data-testid="match-vs"', false)->assertSee('開催前')->assertSee('2026/10/27 05:00')
            ->assertDontSee('data-testid="match-score"', false);
    }

    public function test_search_period_tabs_do_not_use_aria_current(): void
    {
        $this->createMatch();

        $response = $this->get(route('matches.index', ['team' => 'Arsenal']));

        $response->assertOk()->assertSee('今後の試合')->assertSee('過去の試合')->assertDontSee('aria-current', false);
    }

    public function test_search_results_pagination_is_rendered_with_japanese_labels(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->createMatch(['kickoff_at' => now()->addDays($i + 1)]);
        }

        $first = $this->get(route('matches.index', ['team' => 'Arsenal']));
        $first->assertOk()->assertSee('件中')->assertSee('次へ')->assertSee('aria-current="page"', false);

        $this->assertMatchesRegularExpression('#21</span>件中#', $first->getContent());

        $second = $this->get(route('matches.index', ['team' => 'Arsenal', 'page' => 2]));
        $second->assertOk()->assertSee('前へ')->assertSee('rel="prev"', false);
    }

    // ---- 試合詳細 ----

    public function test_show_renders_status_badge_and_reserve_button(): void
    {
        $this->travelTo('2026-10-25 12:00:00');
        $match = $this->createMatch();
        $seat = $this->createBroadcast($match, '予約できる店');

        $response = $this->get(route('matches.show', $match->id));

        $response->assertOk()
            ->assertSee('試合一覧に戻る')
            ->assertSee('開催前')
            ->assertSee('data-testid="broadcast-shop"', false)
            ->assertSee('予約できる店')
            ->assertSee('href="'.route('reservations.create', $seat->broadcast_id).'"', false)
            ->assertSee('予約する')
            ->assertDontSee('予約済み');
    }

    public function test_show_renders_reserved_badge_and_link_instead_of_reserve_button(): void
    {
        $this->travelTo('2026-10-25 12:00:00');
        $match = $this->createMatch();
        $seat = $this->createBroadcast($match, '予約済みの店');
        $reservation = Reservation::unguarded(fn () => Reservation::create([
            'reservation_code' => 'REDESIGN1',
            'user_id' => $this->user->id,
            'broadcast_seat_type_id' => $seat->id,
            'number_of_people' => 1,
            'unit_price' => $seat->price,
            'total_price' => $seat->price,
            'status' => 'confirmed',
            'reserved_at' => now(),
        ]));

        $response = $this->get(route('matches.show', $match->id));

        $response->assertOk()
            ->assertSee('予約済み')
            ->assertSee('予約内容を見る')
            ->assertSee('href="'.route('reservations.show', $reservation->id).'"', false)
            ->assertDontSee('href="'.route('reservations.create', $seat->broadcast_id).'"', false);
    }

    public function test_show_renders_full_badge_without_reserve_button(): void
    {
        $this->travelTo('2026-10-25 12:00:00');
        $match = $this->createMatch();
        $seat = $this->createBroadcast($match, '満席の店', 1);
        Reservation::unguarded(fn () => Reservation::create([
            'reservation_code' => 'REDESIGN2',
            'user_id' => User::factory()->create()->id,
            'broadcast_seat_type_id' => $seat->id,
            'number_of_people' => 1,
            'unit_price' => $seat->price,
            'total_price' => $seat->price,
            'status' => 'confirmed',
            'reserved_at' => now(),
        ]));

        $response = $this->get(route('matches.show', $match->id));

        $response->assertOk()
            ->assertDontSee('href="'.route('reservations.create', $seat->broadcast_id).'"', false);
        $this->assertMatchesRegularExpression('#<span[^>]*bg-error-container[^>]*>\s*満席\s*</span>#', $response->getContent());
    }

    public function test_show_keeps_sort_links_and_marks_current_one(): void
    {
        $this->travelTo('2026-10-25 12:00:00');
        $match = $this->createMatch();
        $this->createBroadcast($match, 'どこかの店');

        $response = $this->get(route('matches.show', ['id' => $match->id, 'sort' => 'price']));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="true"'));
        $this->assertMatchesRegularExpression('#<a href="[^"]+"[^>]*aria-current="true"[^>]*>料金の安い順</a>#', $response->getContent());
    }

    // ---- 店舗詳細・お気に入り登録 ----

    public function test_shop_show_renders(): void
    {
        $shop = Shop::factory()->create([
            'user_id' => User::factory()->create()->id,
            'name' => '刷新確認バー',
            'status' => Shop::STATUS_PUBLISHED,
        ]);

        $this->get(route('shops.show', $shop))->assertOk()->assertSee('刷新確認バー')->assertSee('座席情報')->assertSee('放映予定試合');
    }

    public function test_select_teams_renders_team_cards_with_english_name_when_japanese_name_exists(): void
    {
        $match = $this->createMatch();
        $match->homeTeam->update(['name_ja' => 'アーセナル']);

        $response = $this->get('/select/teams');

        $response->assertOk()->assertSee('最大３チームまで')->assertSee('アーセナル')->assertSee('Arsenal FC')->assertSee('Chelsea FC')
            ->assertSee('name="team_ids[]"', false)->assertSee('登録');
    }
}
