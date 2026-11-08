<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

/**
 * 店舗オーナー用レイアウト（Partner Portal）のナビ・店舗の切り替え・表の横スクロールの確認。
 */
class OwnerLayoutTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->shopOwner()->create();
        $this->shop = Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーA', 'status' => 'published']);
    }

    private function secondShop(): Shop
    {
        return Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーB', 'status' => 'published']);
    }

    /**
     * 店舗の切り替え（nav）の部分だけを取り出す。なければ null。
     */
    private function switcherHtml(string $html): ?string
    {
        return preg_match('#<nav aria-label="店舗の切り替え".*?</nav>#s', $html, $matches) === 1 ? $matches[0] : null;
    }

    public function test_nav_marks_only_current_section_with_aria_current(): void
    {
        $html = $this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<nav data-testid="desktop-nav".*?</nav>#s', $html, $desktop));
        $this->assertSame(1, preg_match('#<details data-testid="mobile-menu".*?</details>#s', $html, $mobile));
        foreach ([$desktop[0], $mobile[0]] as $nav) {
            // 今いる画面（座席管理）だけに aria-current="page" が付く
            $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('owner.seat-types.index'), '#').'"[^>]*aria-current="page"[^>]*>座席管理</a>#', $nav);
            foreach ([
                route('owner.shops.index') => '店舗情報',
                route('owner.broadcasts.index') => '放映管理',
                route('owner.reservations.index') => '予約管理',
                route('mypage') => 'マイページ',
            ] as $url => $label) {
                $this->assertStringContainsString('<a href="'.$url.'"', $nav);
                $this->assertStringContainsString($label, $nav);
            }
            $this->assertStringContainsString('action="'.route('logout').'" method="POST"', $nav);
            $this->assertStringContainsString('name="_token"', $nav);
        }
    }

    public function test_nav_current_section_follows_each_owner_page(): void
    {
        foreach ([
            [route('owner.shops.index'), '店舗情報'],
            [route('owner.shops.create'), '店舗情報'],
            [route('owner.broadcasts.index'), '放映管理'],
            [route('owner.reservations.index'), '予約管理'],
            [route('owner.seat-types.create'), '座席管理'],
        ] as [$url, $label]) {
            $html = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<nav data-testid="desktop-nav".*?</nav>#s', $html, $desktop));
            $this->assertSame(1, substr_count($desktop[0], 'aria-current="page"'), $url);
            $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>'.$label.'</a>#', $desktop[0], $url);
        }
    }

    public function test_header_shows_partner_portal_without_unneeded_badges(): void
    {
        $this->actingAs($this->owner)->get(route('owner.shops.index'))
            ->assertOk()
            ->assertSee('Partner Portal')
            ->assertSee('PITCH &amp; PARLOUR', false)
            ->assertDontSee('公式パートナー');
    }

    public function test_desktop_nav_is_hidden_below_lg_and_mobile_menu_is_hidden_from_lg(): void
    {
        $html = $this->actingAs($this->owner)->get(route('owner.shops.index'))->getContent();

        $this->assertSame(1, preg_match('#<nav data-testid="desktop-nav" class="([^"]*)"#', $html, $desktop));
        $this->assertContains('hidden', explode(' ', $desktop[1]));
        $this->assertContains('lg:flex', explode(' ', $desktop[1]));
        $this->assertSame(1, preg_match('#<details data-testid="mobile-menu" class="([^"]*)"#', $html, $mobile));
        $this->assertContains('lg:hidden', explode(' ', $mobile[1]));
    }

    public function test_shop_switcher_is_hidden_with_one_shop(): void
    {
        foreach ([
            route('owner.seat-types.index'),
            route('owner.broadcasts.index'),
            route('owner.reservations.index'),
        ] as $url) {
            $this->assertNull($this->switcherHtml($this->actingAs($this->owner)->get($url)->assertOk()->getContent()), $url);
        }
    }

    public function test_shop_switcher_is_shown_with_two_shops_and_links_keep_current_page(): void
    {
        $second = $this->secondShop();

        foreach ([
            'owner.seat-types.index',
            'owner.seat-types.create',
            'owner.broadcasts.index',
            'owner.broadcasts.create',
            'owner.reservations.index',
        ] as $routeName) {
            $switcher = $this->switcherHtml($this->actingAs($this->owner)->get(route($routeName))->assertOk()->getContent());

            $this->assertNotNull($switcher, $routeName);
            $this->assertStringContainsString('href="'.e(route($routeName, ['shop' => $this->shop->id])).'"', $switcher);
            $this->assertStringContainsString('href="'.e(route($routeName, ['shop' => $second->id])).'"', $switcher);
            $this->assertStringContainsString('バーA', $switcher);
            $this->assertStringContainsString('バーB', $switcher);
            // JavaScript なしで動く details/summary
            $this->assertStringContainsString('<details', $switcher);
            $this->assertStringContainsString('<summary', $switcher);
        }
    }

    public function test_shop_switcher_keeps_period_and_marks_selected_shop(): void
    {
        $second = $this->secondShop();

        $html = $this->actingAs($this->owner)
            ->get(route('owner.reservations.index', ['shop' => $second->id, 'period' => 'past', 'match' => 1, 'page' => 2]))
            ->assertOk()->getContent();
        $switcher = $this->switcherHtml($html);

        // 表示期間だけを引き継ぎ、試合の絞り込み・ページ番号は店舗ごとに意味が変わるので引き継がない
        $this->assertStringContainsString('href="'.e(route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'past'])).'"', $switcher);
        $this->assertStringNotContainsString('match=', $switcher);
        $this->assertStringNotContainsString('page=', $switcher);
        // 選んでいる店舗（バーB）のリンクだけが強調される
        $this->assertSame(1, substr_count($switcher, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>バーB</a>#', $switcher);
    }

    public function test_shop_switcher_ignores_unknown_period_value(): void
    {
        $this->secondShop();

        $switcher = $this->switcherHtml(
            $this->actingAs($this->owner)->get(route('owner.reservations.index', ['period' => '"><script>']))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('script', $switcher);
        $this->assertStringNotContainsString('period=', $switcher);
    }

    public function test_shop_switcher_is_not_shown_on_pages_without_selected_shop(): void
    {
        $this->secondShop();

        // 店舗情報（一覧・登録）には、選んでいる店舗がない
        $this->assertNull($this->switcherHtml($this->actingAs($this->owner)->get(route('owner.shops.index'))->assertOk()->getContent()));
        $this->assertNull($this->switcherHtml($this->actingAs($this->owner)->get(route('owner.shops.create'))->assertOk()->getContent()));
        // URL に ID を持つ画面（編集）でも出さない
        $this->assertNull($this->switcherHtml($this->actingAs($this->owner)->get(route('owner.shops.edit', $this->shop))->assertOk()->getContent()));
    }

    public function test_shop_switcher_escapes_shop_names(): void
    {
        $this->shop->forceFill(['name' => '<b>悪い店</b>'])->save();
        $this->secondShop();

        $switcher = $this->switcherHtml($this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertOk()->getContent());

        $this->assertStringNotContainsString('<b>悪い店</b>', $switcher);
        $this->assertStringContainsString('&lt;b&gt;悪い店&lt;/b&gt;', $switcher);
    }

    public function test_list_tables_scroll_inside_wrapper_not_the_whole_page(): void
    {
        SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => 'カウンター席']);

        foreach ([
            route('owner.shops.index'),
            route('owner.seat-types.index'),
        ] as $url) {
            $html = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
            // 表は、横スクロールする枠（overflow-x-auto）の中に入っている
            $this->assertMatchesRegularExpression('#<div data-testid="owner-table-scroll" class="[^"]*\boverflow-x-auto\b[^"]*">\s*<table#', $html, $url);
        }
    }

    public function test_reservation_and_broadcast_lists_tables_scroll_inside_wrapper(): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => now()->addDays(3)]);
        $broadcast = Broadcast::factory()->forMatch($match)->create(['shop_id' => $this->shop->id, 'status' => Broadcast::STATUS_PUBLISHED]);
        $seatType = SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => 'カウンター席', 'default_capacity' => 10, 'default_price' => 3000, 'is_active' => true]);
        $seat = BroadcastSeatType::query()->firstOrCreate(
            ['broadcast_id' => $broadcast->id, 'seat_type_id' => $seatType->id],
            BroadcastSeatType::factory()->forSeatType($seatType)->make()->getAttributes(),
        );
        Reservation::factory()->state(['broadcast_seat_type_id' => $seat->id, 'user_id' => User::factory()->create()->id])->create();

        foreach ([
            route('owner.reservations.index'),
            route('owner.broadcasts.index'),
        ] as $url) {
            $html = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
            // 行がある画面では、表は必ず横スクロールする枠の中に入っている
            $this->assertStringContainsString('<table', $html, $url);
            $this->assertMatchesRegularExpression('#<div data-testid="owner-table-scroll" class="[^"]*\boverflow-x-auto\b[^"]*">\s*<table#', $html, $url);
        }
    }

    public function test_shop_badges_use_shared_variants(): void
    {
        Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーC', 'status' => Shop::STATUS_DRAFT]);

        $html = $this->actingAs($this->owner)->get(route('owner.shops.index'))->assertOk()->getContent();

        // 公開は success（緑）、非公開は neutral（灰）
        $this->assertMatchesRegularExpression('#bg-tertiary-fixed[^"]*"[^>]*>公開</span>#', $html);
        $this->assertMatchesRegularExpression('#bg-surface-container-high[^"]*"[^>]*>非公開</span>#', $html);
    }

    public function test_seat_type_badges_use_shared_variants(): void
    {
        SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => '有効席', 'is_active' => true]);
        SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => '無効席', 'is_active' => false]);

        $html = $this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#bg-tertiary-fixed[^"]*"[^>]*>有効</span>#', $html);
        $this->assertMatchesRegularExpression('#bg-surface-container-high[^"]*"[^>]*>無効</span>#', $html);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function reservationStatusBadges(): array
    {
        return [
            '予約確定' => [Reservation::STATUS_CONFIRMED, '予約確定', 'bg-tertiary-fixed'],
            '来店済み' => [Reservation::STATUS_VISITED, '来店済み', 'bg-tertiary-fixed'],
            '来店なし' => [Reservation::STATUS_NO_SHOW, '来店なし', 'bg-error-container'],
            'キャンセル済み' => [Reservation::STATUS_CANCELLED, 'キャンセル済み', 'bg-surface-container-high'],
        ];
    }

    #[DataProvider('reservationStatusBadges')]
    public function test_reservation_status_badges_use_shared_variants_in_list_and_detail(string $status, string $label, string $colorClass): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => now()->addDays(3)]);
        $broadcast = Broadcast::factory()->forMatch($match)->create(['shop_id' => $this->shop->id, 'status' => Broadcast::STATUS_PUBLISHED]);
        $seatType = SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => 'カウンター席', 'default_capacity' => 10, 'default_price' => 3000, 'is_active' => true]);
        $seat = BroadcastSeatType::query()->firstOrCreate(
            ['broadcast_id' => $broadcast->id, 'seat_type_id' => $seatType->id],
            BroadcastSeatType::factory()->forSeatType($seatType)->make()->getAttributes(),
        );
        $reservation = Reservation::factory()->state([
            'broadcast_seat_type_id' => $seat->id,
            'user_id' => User::factory()->create()->id,
            'status' => $status,
        ])->create();
        $pattern = '#'.$colorClass.'[^"]*"[^>]*>'.$label.'</span>#';

        $this->assertMatchesRegularExpression($pattern, $this->actingAs($this->owner)->get(route('owner.reservations.index'))->assertOk()->getContent());
        $this->assertMatchesRegularExpression($pattern, $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))->assertOk()->getContent());
    }
}
