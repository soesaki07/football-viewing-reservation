<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopControllerTest extends TestCase
{
    use RefreshDatabase;

    private Competition $competition;

    private Team $homeTeam;

    private Team $awayTeam;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-10-25 12:00 UTC（JST 21:00）を「今」とする
        $this->travelTo('2026-10-25 12:00:00');
        $this->actingAs(User::factory()->create());

        $this->competition = Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'area_name' => 'England',
            'is_active' => true,
        ]);
        $this->homeTeam = Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC']);
        $this->awayTeam = Team::create(['external_team_id' => 2, 'name' => 'Chelsea FC']);
    }

    private function createShop(array $overrides = []): Shop
    {
        return Shop::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'name' => 'テストスポーツバー',
            'postal_code' => '150-0001',
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'address_line' => '神宮前1-2-3',
            'latitude' => null,
            'longitude' => null,
            'google_place_id' => null,
            'phone_number' => '03-1234-5678',
            'description' => '大型スクリーンで観戦できます。',
            'opening_time' => '17:00:00',
            'closing_time' => '23:00:00',
            'website_url' => null,
            'status' => Shop::STATUS_PUBLISHED,
        ], $overrides));
    }

    private function createMatch(string $kickoffAt, array $overrides = []): FootballMatch
    {
        static $externalId = 800000;
        $externalId++;

        return FootballMatch::create(array_merge([
            'external_match_id' => $externalId,
            'competition_id' => $this->competition->id,
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'season_start_year' => 2026,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => $kickoffAt,
            'status' => 'TIMED',
            'last_api_synced_at' => now(),
        ], $overrides));
    }

    private function createBroadcast(Shop $shop, FootballMatch $match, string $status = Broadcast::STATUS_PUBLISHED): Broadcast
    {
        return Broadcast::factory()->forMatch($match)->create([
            'shop_id' => $shop->id,
            'status' => $status,
        ]);
    }

    // ID の部分一致（/matches/2 と /matches/23 など）を避けるため、href 属性ごと比べる
    private function matchLink(FootballMatch $match): string
    {
        return 'href="'.route('matches.show', $match->id).'"';
    }

    public function test_show_displays_published_shop(): void
    {
        $shop = $this->createShop();

        $response = $this->get(route('shops.show', $shop));

        $response->assertOk();
        $response->assertSee('テストスポーツバー');
        $response->assertSee('大型スクリーンで観戦できます。');
        $response->assertSee('〒150-0001');
        $response->assertSee('東京都渋谷区神宮前1-2-3');
        $response->assertSee('03-1234-5678');
        $response->assertSee('17:00〜23:00');
    }

    public function test_show_returns_404_for_draft_shop(): void
    {
        $shop = $this->createShop(['status' => 'draft']);

        $this->get(route('shops.show', $shop))->assertNotFound();
    }

    public function test_show_returns_404_for_deleted_shop(): void
    {
        $shop = $this->createShop();
        $shop->delete();

        $this->get(route('shops.show', $shop))->assertNotFound();
    }

    public function test_show_returns_404_for_missing_shop(): void
    {
        $this->get('/shops/999999')->assertNotFound();
        $this->get('/shops/abc')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $shop = $this->createShop();
        auth()->logout();

        $this->get(route('shops.show', $shop))->assertRedirect(route('login'));
    }

    public function test_business_hours_across_midnight_are_shown_with_next_day_mark(): void
    {
        $shop = $this->createShop(['opening_time' => '18:00:00', 'closing_time' => '01:00:00']);

        $this->get(route('shops.show', $shop))->assertSee('18:00〜翌1:00');
    }

    public function test_business_hours_are_hidden_when_not_set(): void
    {
        $shop = $this->createShop(['closing_time' => null]);

        $this->get(route('shops.show', $shop))->assertDontSee('営業時間');
    }

    public function test_website_url_is_linked_only_for_http_or_https(): void
    {
        $safeShop = $this->createShop(['website_url' => 'https://example.com/bar']);
        $unsafeShop = $this->createShop(['website_url' => 'javascript:alert(1)']);

        $this->get(route('shops.show', $safeShop))
            ->assertSee('href="https://example.com/bar"', false);
        $this->get(route('shops.show', $unsafeShop))
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('Webサイト');
    }

    public function test_map_is_embedded_when_api_key_is_set(): void
    {
        config(['services.google_maps.embed_key' => 'test-embed-key']);
        $shop = $this->createShop(['google_place_id' => 'ChIJtest']);

        $response = $this->get(route('shops.show', $shop));

        $response->assertSee('https://www.google.com/maps/embed/v1/place?key=test-embed-key&amp;q=place_id%3AChIJtest', false);
        $response->assertDontSee('Google マップで開く');
    }

    public function test_map_link_is_shown_when_api_key_is_not_set(): void
    {
        config(['services.google_maps.embed_key' => null]);
        $shop = $this->createShop();

        $response = $this->get(route('shops.show', $shop));

        $response->assertSee('Google マップで開く');
        $response->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false);
        $response->assertDontSee('<iframe', false);
    }

    public function test_only_active_seat_types_are_shown(): void
    {
        $shop = $this->createShop();
        SeatType::factory()->create(['shop_id' => $shop->id, 'name' => 'カウンター席', 'description' => '一人でも気軽に', 'default_price' => 3000]);
        SeatType::factory()->create(['shop_id' => $shop->id, 'name' => '休止中の席', 'default_price' => 1000, 'is_active' => false]);

        $response = $this->get(route('shops.show', $shop));

        $response->assertSee('カウンター席');
        $response->assertSee('一人でも気軽に');
        $response->assertSee('3,000円');
        $response->assertDontSee('休止中の席');
    }

    public function test_upcoming_matches_show_only_future_published_broadcasts_in_kickoff_order(): void
    {
        $shop = $this->createShop();
        $otherShop = $this->createShop(['name' => '別の店']);

        $later = $this->createMatch('2026-10-27 19:00:00');
        $sooner = $this->createMatch('2026-10-26 15:00:00');
        $finished = $this->createMatch('2026-10-24 19:00:00', ['status' => 'FINISHED']);
        $draft = $this->createMatch('2026-10-28 19:00:00');
        $otherShopMatch = $this->createMatch('2026-10-29 19:00:00');
        $this->createBroadcast($shop, $later);
        $this->createBroadcast($shop, $sooner);
        $this->createBroadcast($shop, $finished);
        $this->createBroadcast($shop, $draft, 'draft');
        $this->createBroadcast($otherShop, $otherShopMatch);

        $response = $this->get(route('shops.show', $shop));

        // キックオフは JST で表示し、早い順に並ぶ
        $response->assertSeeInOrder(['2026/10/27 00:00', '2026/10/28 04:00']);
        $response->assertSee($this->matchLink($sooner), false);
        $response->assertSee($this->matchLink($later), false);
        $response->assertDontSee($this->matchLink($finished), false);
        $response->assertDontSee($this->matchLink($draft), false);
        $response->assertDontSee($this->matchLink($otherShopMatch), false);
    }

    public function test_upcoming_matches_are_limited_to_ten(): void
    {
        $shop = $this->createShop();
        $matches = [];
        for ($day = 1; $day <= 11; $day++) {
            $matches[$day] = $this->createMatch(sprintf('2026-11-%02d 19:00:00', $day));
            $this->createBroadcast($shop, $matches[$day]);
        }

        $response = $this->get(route('shops.show', $shop));

        $response->assertSee($this->matchLink($matches[10]), false);
        $response->assertDontSee($this->matchLink($matches[11]), false);
    }

    public function test_seat_types_and_matches_are_in_accordions_with_counts(): void
    {
        $shop = $this->createShop();
        SeatType::factory()->create(['shop_id' => $shop->id, 'name' => 'カウンター席', 'default_price' => 3000]);
        SeatType::factory()->create(['shop_id' => $shop->id, 'name' => 'テーブル席', 'default_price' => 4000]);
        $this->createBroadcast($shop, $this->createMatch('2026-10-26 15:00:00'));

        $response = $this->get(route('shops.show', $shop));

        // 閉じた状態（open 属性なし）の details が2つあり、見出しに件数が出る
        $response->assertSee('<details class="group', false);
        $response->assertDontSee('<details open', false);
        $response->assertSeeInOrder(['座席情報', '（2件）', '放映予定試合', '（1件）']);
    }

    public function test_empty_messages_are_shown_without_seat_types_and_matches(): void
    {
        $shop = $this->createShop();

        $response = $this->get(route('shops.show', $shop));

        $response->assertSee('座席情報は準備中です。');
        $response->assertSee('放映予定の試合はありません。');
    }
}
