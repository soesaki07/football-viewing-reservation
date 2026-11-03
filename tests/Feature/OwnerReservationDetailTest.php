<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class OwnerReservationDetailTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-31 12:00:00');

        $this->owner = User::factory()->shopOwner()->create();
        $this->shop = Shop::factory()->create(['user_id' => $this->owner->id, 'status' => 'published']);
    }

    /**
     * 店舗の放映に対する予約を作る。既定はキックオフ済み（1時間前）の confirmed。
     *
     * @param  array<string, mixed>  $state
     */
    private function addReservation(?Shop $shop = null, string $kickoffAt = '2026-10-31 11:00:00', array $state = []): Reservation
    {
        $shop ??= $this->shop;
        $match = $this->createFootballMatch(['kickoff_at' => $kickoffAt]);
        $broadcast = Broadcast::factory()->forMatch($match)->create(['shop_id' => $shop->id, 'status' => Broadcast::STATUS_PUBLISHED]);
        $seatType = SeatType::query()->where('shop_id', $shop->id)->where('name', 'カウンター席')->first()
            ?? SeatType::factory()->create(['shop_id' => $shop->id, 'name' => 'カウンター席', 'default_capacity' => 10, 'default_price' => 3000, 'is_active' => true]);
        $broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);

        return Reservation::factory()->create(array_merge([
            'broadcast_seat_type_id' => $broadcastSeatType->id,
        ], $state));
    }

    public function test_owner_sees_all_detail_items_in_jst(): void
    {
        $reservation = $this->addReservation(state: [
            'reservation_code' => 'DETAIL01',
            'user_id' => User::factory()->create(['name' => '山田太郎', 'email' => 'secret-customer@example.com'])->id,
            'number_of_people' => 3,
            'unit_price' => 1500,
            'total_price' => 4500,
            'customer_note' => "窓際の席を希望\n誕生日です",
            'reserved_at' => '2026-10-30 00:30:00',
        ]);
        $reservation->load('broadcastSeatType.broadcast.footballMatch.homeTeam');
        $kickoffJst = $reservation->broadcastSeatType->broadcast->footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i');

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))
            ->assertOk()
            ->assertSeeInOrder(['予約コード', 'DETAIL01', 'ユーザー', '山田太郎', '試合', $kickoffJst, '座席', 'カウンター席', '人数', '3人', '単価', '1,500円', '合計金額', '4,500円', '備考'])
            ->assertSee("class=\"whitespace-pre-line\">窓際の席を希望\n誕生日です</dd>", false)
            ->assertSee('2026/10/30 09:30')
            ->assertSee('予約日時')
            ->assertSee('確定')
            ->assertDontSee('来店日時')
            ->assertDontSee('キャンセル日時')
            ->assertDontSee('secret-customer@example.com');
    }

    public function test_empty_note_shows_none(): void
    {
        $reservation = $this->addReservation(state: ['customer_note' => null]);

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))
            ->assertOk()->assertSeeInOrder(['備考', 'なし']);
    }

    public function test_visited_at_is_shown_only_for_visited(): void
    {
        $reservation = $this->addReservation(state: ['status' => Reservation::STATUS_VISITED, 'visited_at' => '2026-10-31 03:15:00']);

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))
            ->assertOk()->assertSeeInOrder(['来店日時', '2026/10/31 12:15'])->assertDontSee('キャンセル日時');
    }

    public function test_cancelled_at_is_shown_only_for_cancelled(): void
    {
        $reservation = $this->addReservation(state: ['status' => Reservation::STATUS_CANCELLED, 'cancelled_at' => '2026-10-29 15:20:00']);

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))
            ->assertOk()->assertSeeInOrder(['キャンセル日時', '2026/10/30 00:20'])->assertDontSee('来店日時');
    }

    public function test_note_and_user_name_are_escaped(): void
    {
        $reservation = $this->addReservation(state: [
            'user_id' => User::factory()->create(['name' => '<script>alert(1)</script>'])->id,
            'customer_note' => '<script>alert(2)</script>',
        ]);

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<script>alert(2)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false);
    }

    public function test_unpublished_shops_reservation_is_visible(): void
    {
        $this->shop->update(['status' => 'unpublished']);
        $reservation = $this->addReservation();

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $reservation = $this->addReservation();

        $this->get(route('owner.reservations.show', $reservation))->assertRedirect(route('login'));
    }

    public function test_user_and_admin_get_403(): void
    {
        $reservation = $this->addReservation();
        $this->actingAs(User::factory()->create())->get(route('owner.reservations.show', $reservation))->assertForbidden();

        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $this->actingAs($admin)->get(route('owner.reservations.show', $reservation))->assertForbidden();
    }

    public function test_other_shops_reservation_is_404(): void
    {
        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id, 'status' => 'published']);
        $reservation = $this->addReservation($otherShop);

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))->assertNotFound();
    }

    public function test_deleted_shops_reservation_is_404(): void
    {
        $reservation = $this->addReservation();
        $this->shop->delete();

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))->assertNotFound();
    }

    public function test_missing_reservation_is_404(): void
    {
        $this->actingAs($this->owner)->get(route('owner.reservations.show', 999999))->assertNotFound();
    }

    public function test_back_link_keeps_valid_conditions(): void
    {
        $reservation = $this->addReservation();
        $expected = route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'past', 'match' => 5, 'page' => 2]);

        $this->actingAs($this->owner)->get(route('owner.reservations.show', ['reservation' => $reservation, 'period' => 'past', 'match' => '5', 'page' => '2']))
            ->assertOk()
            ->assertSee('href="'.e($expected).'"', false);
    }

    public function test_back_link_drops_invalid_conditions(): void
    {
        $reservation = $this->addReservation();
        $expected = route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'upcoming']);

        foreach ([
            ['period' => 'http://evil.example', 'match' => 'abc', 'page' => '-1'],
            ['period' => ['x'], 'match' => ['1'], 'page' => ['2']],
            ['match' => '0', 'page' => '1.5'],
        ] as $query) {
            $this->actingAs($this->owner)->get(route('owner.reservations.show', ['reservation' => $reservation] + $query))
                ->assertOk()
                ->assertSee('href="'.e($expected).'"', false)
                ->assertDontSee('evil.example');
        }
    }

    public function test_list_links_reservation_code_to_detail_with_conditions(): void
    {
        $reservation = $this->addReservation(state: ['reservation_code' => 'LINKCODE']);
        $url = route('owner.reservations.show', ['reservation' => $reservation, 'period' => 'past', 'page' => 1]);

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['period' => 'past']))
            ->assertOk()
            ->assertSee('<a href="'.e($url).'"', false);
    }

    public function test_update_from_detail_returns_to_detail_with_message(): void
    {
        $reservation = $this->addReservation();

        $this->actingAs($this->owner)
            ->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'visited', 'return' => 'show', 'period' => 'past', 'match' => '5', 'page' => '2'])
            ->assertRedirect(route('owner.reservations.show', ['reservation' => $reservation, 'shop' => $this->shop->id, 'period' => 'past', 'match' => 5, 'page' => 2]))
            ->assertSessionHas('success', "予約 {$reservation->reservation_code} を来店済みにしました。");
        $this->assertSame(Reservation::STATUS_VISITED, $reservation->refresh()->status);

        $this->actingAs($this->owner)->followingRedirects()
            ->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'no_show', 'return' => 'show'])
            ->assertOk()
            ->assertSee("予約 {$reservation->reservation_code} を来店なしにしました。")
            ->assertSee('一覧に戻る');
    }

    public function test_failed_update_from_detail_returns_to_detail_with_error(): void
    {
        $reservation = $this->addReservation(kickoffAt: '2026-11-05 12:00:00');

        $this->actingAs($this->owner)->from('http://evil.example/back')
            ->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'visited', 'return' => 'show'])
            ->assertRedirect(route('owner.reservations.show', ['reservation' => $reservation, 'shop' => $this->shop->id, 'period' => 'upcoming']))
            ->assertSessionHas('error');

        $this->actingAs($this->owner)
            ->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'foo', 'return' => 'show'])
            ->assertRedirect(route('owner.reservations.show', ['reservation' => $reservation, 'shop' => $this->shop->id, 'period' => 'upcoming']))
            ->assertSessionHasErrors('status');
    }

    public function test_update_from_list_still_returns_to_list_and_unknown_return_is_ignored(): void
    {
        $reservation = $this->addReservation();
        $listUrl = route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'upcoming']);

        $this->actingAs($this->owner)
            ->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'visited'])
            ->assertRedirect($listUrl);

        foreach (['http://evil.example', '//evil.example', ['show'], 'SHOW'] as $return) {
            $this->actingAs($this->owner)
                ->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'no_show', 'return' => $return])
                ->assertRedirect($listUrl);
        }
    }

    public function test_detail_shows_buttons_per_status_same_as_list(): void
    {
        $confirmed = $this->addReservation();
        $visited = $this->addReservation(state: ['status' => Reservation::STATUS_VISITED, 'visited_at' => now()]);
        $noShow = $this->addReservation(state: ['status' => Reservation::STATUS_NO_SHOW]);
        $url = fn (Reservation $r) => route('owner.reservations.visit-status', $r);

        $this->actingAs($this->owner)->get(route('owner.reservations.show', $confirmed))->assertOk()
            ->assertSee('action="'.e($url($confirmed)).'"', false)
            ->assertSee('name="return" value="show"', false)
            ->assertSeeInOrder(['value="visited"', '来店済み</button>', 'value="no_show"', '来店なし</button>'], false)
            ->assertSee("予約 {$confirmed->reservation_code} を来店済みにしますか？");
        $this->actingAs($this->owner)->get(route('owner.reservations.show', $visited))->assertOk()
            ->assertSee('来店なしに変更</button>', false)->assertDontSee('来店済みに変更');
        $this->actingAs($this->owner)->get(route('owner.reservations.show', $noShow))->assertOk()
            ->assertSee('来店済みに変更</button>', false)->assertDontSee('来店なしに変更');
    }

    public function test_detail_shows_no_button_before_kickoff_or_for_cancelled(): void
    {
        $before = $this->addReservation(kickoffAt: '2026-11-05 12:00:00');
        $cancelled = $this->addReservation(state: ['status' => Reservation::STATUS_CANCELLED, 'cancelled_at' => now()]);

        foreach ([$before, $cancelled] as $reservation) {
            $this->actingAs($this->owner)->get(route('owner.reservations.show', $reservation))
                ->assertOk()->assertDontSee('visit-status')->assertDontSee('data-confirm', false);
        }
    }

    public function test_detail_loads_relations_in_a_fixed_number_of_queries(): void
    {
        $reservation = $this->addReservation();
        $this->actingAs($this->owner);

        DB::enableQueryLog();
        $this->get(route('owner.reservations.show', $reservation))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 予約・座席・放映・店舗（認可）、ユーザー・座席種別・試合・チーム2件（詳細の表示）、お気に入りチーム（ナビ）の10回（目安）。
        // 同じ SQL を繰り返さない（遅延ロードがあるとここが増える）
        $this->assertLessThanOrEqual(12, count($queries));
        $sqls = array_column($queries, 'query');
        $this->assertSame(count($sqls), count(array_unique($sqls)));

        // 表示用の関連（ユーザー・座席種別・試合・ホームとアウェイのチーム）は、1件ずつの遅延ロード（`= ? limit 1`）ではなく
        // まとめ読み（`in (...)`）で読んでいる。単一のモデルでは preventLazyLoading が効かず、件数も同じになるため SQL の形で確かめる
        $this->assertSame(5, $this->countEagerLoads($sqls));
    }

    /**
     * 表示用の関連テーブルを `in (...)` で読んでいる SQL の数。識別子の囲み（SQLite は ""、MySQL は ``）に左右されない。
     *
     * @param  list<string>  $sqls
     */
    private function countEagerLoads(array $sqls): int
    {
        $normalized = array_map(fn (string $sql) => str_replace(['"', '`'], '', $sql), $sqls);

        return count(array_filter($normalized, fn (string $sql) => preg_match('/^select .* from (users|seat_types|football_matches|teams) where [a-z_]+\.id in \(/', $sql) === 1));
    }

    public function test_eager_load_detection_matches_mysql_grammar(): void
    {
        // DB には接続せず、MySQL の文法で SQL を組み立てる（識別子はバッククォート）
        $mysql = DB::connection('mysql');
        $sqls = [
            $mysql->table('users')->select('id', 'name')->whereIn('users.id', [2])->toSql(),
            $mysql->table('seat_types')->whereIn('seat_types.id', [1])->toSql(),
            $mysql->table('football_matches')->whereIn('football_matches.id', [1])->toSql(),
            $mysql->table('teams')->whereIn('teams.id', [1])->toSql(),
            $mysql->table('teams')->whereIn('teams.id', [2])->toSql(),
            // 遅延ロードの形は数えない
            $mysql->table('seat_types')->where('seat_types.id', 1)->limit(1)->toSql(),
        ];

        $this->assertStringContainsString('`users`', $sqls[0]);
        $this->assertSame(5, $this->countEagerLoads($sqls));
    }
}
