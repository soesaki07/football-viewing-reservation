<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class OwnerReservationTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        // 今後・過去の境目を扱うので現在時刻を固定する
        $this->travelTo('2026-10-31 12:00:00');

        $this->owner = User::factory()->shopOwner()->create();
        $this->shop = Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーA', 'status' => 'published']);
    }

    /**
     * 店舗の放映に対する予約を作る。$match を省略すると、指定したキックオフ時刻の試合を新しく作る。
     *
     * @param  array<string, mixed>  $reservationState
     */
    private function addReservation(Shop $shop, string $kickoffAt = '2026-11-05 12:00:00', array $reservationState = [], ?FootballMatch $match = null, string $seatName = 'カウンター席'): Reservation
    {
        $match ??= $this->createFootballMatch(['kickoff_at' => $kickoffAt]);
        $broadcast = Broadcast::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'football_match_id' => $match->id],
            Broadcast::factory()->forMatch($match)->make(['shop_id' => $shop->id, 'status' => Broadcast::STATUS_PUBLISHED])->getAttributes(),
        );
        $seatType = SeatType::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => $seatName],
            ['default_capacity' => 10, 'default_price' => 3000, 'is_active' => true],
        );
        $broadcastSeatType = BroadcastSeatType::query()->firstOrCreate(
            ['broadcast_id' => $broadcast->id, 'seat_type_id' => $seatType->id],
            BroadcastSeatType::factory()->forSeatType($seatType)->make()->getAttributes(),
        );

        return Reservation::factory()->state(array_merge([
            'broadcast_seat_type_id' => $broadcastSeatType->id,
            'user_id' => User::factory()->create()->id,
        ], $reservationState))->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('owner.reservations.index'))->assertRedirect(route('login'));
    }

    public function test_user_and_admin_get_403(): void
    {
        $this->actingAs(User::factory()->create())->get(route('owner.reservations.index'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $this->actingAs($admin)->get(route('owner.reservations.index'))->assertForbidden();
    }

    public function test_shows_own_shop_reservation_without_user_email(): void
    {
        $customer = User::factory()->create(['name' => '山田太郎', 'email' => 'secret-taro@example.com']);
        $this->addReservation($this->shop, '2026-11-05 12:00:00', [
            'user_id' => $customer->id,
            'reservation_code' => 'ABCD1234',
            'number_of_people' => 3,
        ], null, 'ソファ席');

        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSeeText('ABCD1234')
            ->assertSeeText('山田太郎')
            ->assertSeeText('Arsenal FC vs Chelsea FC')
            ->assertSeeText('2026/11/05 21:00')
            ->assertSeeText('ソファ席')
            ->assertSeeText('3人')
            ->assertSeeText('予約確定')
            ->assertDontSee('secret-taro@example.com');
    }

    public function test_other_shop_reservations_are_not_shown_even_for_same_match(): void
    {
        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $match = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        $this->addReservation($this->shop, reservationState: ['reservation_code' => 'MINE1111'], match: $match);
        $this->addReservation($otherShop, reservationState: ['reservation_code' => 'OTHR2222'], match: $match);

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['match' => $match->id]))
            ->assertOk()
            ->assertSeeText('MINE1111')
            ->assertDontSeeText('OTHR2222');
    }

    public function test_other_owners_shop_returns_404(): void
    {
        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['shop' => $otherShop->id]))->assertNotFound();
    }

    public function test_missing_and_deleted_shop_return_404(): void
    {
        $deleted = Shop::factory()->create(['user_id' => $this->owner->id]);
        $deleted->delete();

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['shop' => 999999]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['shop' => $deleted->id]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['shop' => 'abc']))->assertNotFound();
    }

    public function test_shop_tabs_shown_only_with_two_or_more_shops(): void
    {
        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertDontSee('aria-label="店舗の切り替え"', false);

        $second = Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーB', 'status' => 'draft']);
        $this->addReservation($second, reservationState: ['reservation_code' => 'SECOND99']);
        $this->addReservation($this->shop, reservationState: ['reservation_code' => 'FIRST111']);

        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSee('aria-label="店舗の切り替え"', false)
            ->assertSeeText('バーB')
            ->assertSeeText('FIRST111')
            ->assertDontSeeText('SECOND99');

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['shop' => $second->id]))
            ->assertOk()
            ->assertSeeText('SECOND99')
            ->assertDontSeeText('FIRST111');
    }

    public function test_owner_without_shops_sees_message(): void
    {
        $owner = User::factory()->shopOwner()->create();

        $this->actingAs($owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSeeText('店舗が登録されていません。');
    }

    public function test_upcoming_and_past_split_at_kickoff_boundary_with_ordering(): void
    {
        $this->addReservation($this->shop, '2026-10-31 12:00:00', ['reservation_code' => 'EXACT000']);
        $this->addReservation($this->shop, '2026-10-31 11:59:59', ['reservation_code' => 'PAST1SEC']);
        $this->addReservation($this->shop, '2026-11-10 12:00:00', ['reservation_code' => 'LATER000']);
        $this->addReservation($this->shop, '2026-10-01 12:00:00', ['reservation_code' => 'OLDER000']);

        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSeeTextInOrder(['EXACT000', 'LATER000'])
            ->assertDontSeeText('PAST1SEC')
            ->assertDontSeeText('OLDER000');

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['period' => 'past']))
            ->assertOk()
            ->assertSeeTextInOrder(['PAST1SEC', 'OLDER000'])
            ->assertDontSeeText('EXACT000')
            ->assertDontSeeText('LATER000');
    }

    public function test_invalid_period_falls_back_to_upcoming(): void
    {
        $this->addReservation($this->shop, '2026-11-05 12:00:00', ['reservation_code' => 'FUTURE11']);
        $this->addReservation($this->shop, '2026-10-01 12:00:00', ['reservation_code' => 'PASTPAST']);

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['period' => 'bogus']))
            ->assertOk()
            ->assertSeeText('FUTURE11')
            ->assertDontSeeText('PASTPAST');
    }

    public function test_same_kickoff_is_ordered_by_reservation_id_desc(): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        $this->addReservation($this->shop, reservationState: ['reservation_code' => 'FIRSTONE'], match: $match);
        $this->addReservation($this->shop, reservationState: ['reservation_code' => 'SECONDON'], match: $match);

        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertSeeTextInOrder(['SECONDON', 'FIRSTONE']);
    }

    public function test_filters_by_match_and_ignores_other_shops_match(): void
    {
        $matchA = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        $matchB = $this->createFootballMatch(['kickoff_at' => '2026-11-06 12:00:00']);
        $this->addReservation($this->shop, reservationState: ['reservation_code' => 'MATCHAAA'], match: $matchA);
        $this->addReservation($this->shop, reservationState: ['reservation_code' => 'MATCHBBB'], match: $matchB);

        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $otherMatch = $this->createFootballMatch(['kickoff_at' => '2026-11-07 12:00:00']);
        $this->addReservation($otherShop, reservationState: ['reservation_code' => 'OTHERSHP'], match: $otherMatch);

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['match' => $matchA->id]))
            ->assertOk()
            ->assertSeeText('MATCHAAA')
            ->assertDontSeeText('MATCHBBB');

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['match' => $otherMatch->id]))
            ->assertOk()
            ->assertDontSeeText('OTHERSHP')
            ->assertSeeText('条件に合う予約はありません。');
    }

    public function test_match_options_are_limited_to_own_shop_and_selected_period(): void
    {
        $upcoming = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        $past = $this->createFootballMatch(['kickoff_at' => '2026-10-01 12:00:00']);
        $this->addReservation($this->shop, match: $upcoming);
        $this->addReservation($this->shop, match: $past);
        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $otherMatch = $this->createFootballMatch(['kickoff_at' => '2026-11-09 12:00:00']);
        $this->addReservation($otherShop, match: $otherMatch);

        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSee('<option value="'.$upcoming->id.'"', false)
            ->assertDontSee('<option value="'.$past->id.'"', false)
            ->assertDontSee('<option value="'.$otherMatch->id.'"', false)
            ->assertSeeText('すべての試合');

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['period' => 'past']))
            ->assertSee('<option value="'.$past->id.'"', false)
            ->assertDontSee('<option value="'.$upcoming->id.'"', false);
    }

    public function test_cancelled_reservation_is_shown_with_label(): void
    {
        $this->addReservation($this->shop, reservationState: ['reservation_code' => 'CANCEL11', 'status' => Reservation::STATUS_CANCELLED]);

        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSeeText('CANCEL11')
            ->assertSeeText('キャンセル済み');
    }

    public function test_empty_message_when_no_reservations(): void
    {
        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSeeText('条件に合う予約はありません。');
    }

    public function test_paginates_20_per_page_and_keeps_query(): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        for ($i = 1; $i <= 21; $i++) {
            $this->addReservation($this->shop, reservationState: ['reservation_code' => sprintf('PAGE%04d', $i)], match: $match);
        }

        // ID の降順なので、最初に作った1件目だけが2ページ目に回る
        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()
            ->assertSeeText('PAGE0021')
            ->assertDontSeeText('PAGE0001');

        $response = $this->actingAs($this->owner)->get(route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'upcoming', 'match' => $match->id, 'page' => 2]));
        $response->assertOk()->assertSeeText('PAGE0001')->assertDontSeeText('PAGE0021');

        $first = $this->actingAs($this->owner)->get(route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'upcoming', 'match' => $match->id]));
        $this->assertStringContainsString('page=2', $first->getContent());
        $this->assertStringContainsString('match='.$match->id, $first->getContent());
        $this->assertStringContainsString('period=upcoming', $first->getContent());
    }

    public function test_header_link_only_for_shop_owner(): void
    {
        $this->actingAs($this->owner)->get(route('mypage'))
            ->assertSee('href="'.route('owner.reservations.index').'"', false)
            ->assertSeeText('予約管理');

        $this->actingAs(User::factory()->create())->get(route('mypage'))
            ->assertDontSeeText('予約管理');
    }

    public function test_query_count_does_not_grow_with_reservations(): void
    {
        $this->addReservation($this->shop);
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('owner.reservations.index'))->assertOk();

            return count(DB::getQueryLog());
        };
        // 1回目はログインユーザーの関連の読み込みなどが入るので、ならしてから数える
        $count();
        $before = $count();

        for ($i = 0; $i < 5; $i++) {
            $this->addReservation($this->shop);
        }
        $this->assertSame($before, $count());
    }
}
