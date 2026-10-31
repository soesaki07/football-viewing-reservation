<?php

namespace Tests\Feature;

use App\Exceptions\ReservationException;
use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class OwnerVisitStatusTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        // キックオフの境目を扱うので現在時刻を固定する
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
        $seatType = SeatType::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => 'カウンター席'],
            ['default_capacity' => 10, 'default_price' => 3000, 'is_active' => true],
        );
        $broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);

        return Reservation::factory()->create(array_merge([
            'broadcast_seat_type_id' => $broadcastSeatType->id,
        ], $state));
    }

    private function update(Reservation $reservation, string $status, ?User $user = null, array $extra = []): TestResponse
    {
        return $this->actingAs($user ?? $this->owner)
            ->patch(route('owner.reservations.visit-status', $reservation), ['status' => $status, ...$extra]);
    }

    public function test_confirmed_after_kickoff_becomes_visited_with_visited_at(): void
    {
        $reservation = $this->addReservation();

        $this->update($reservation, 'visited')
            ->assertRedirect(route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'upcoming']))
            ->assertSessionHas('success', "予約 {$reservation->reservation_code} を来店済みにしました。");

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_VISITED, $reservation->status);
        $this->assertTrue($reservation->visited_at->equalTo(now()));
    }

    public function test_confirmed_after_kickoff_becomes_no_show_without_visited_at(): void
    {
        $reservation = $this->addReservation();

        $this->update($reservation, 'no_show')
            ->assertSessionHas('success', "予約 {$reservation->reservation_code} を来店なしにしました。");

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_NO_SHOW, $reservation->status);
        $this->assertNull($reservation->visited_at);
    }

    public function test_visited_and_no_show_can_be_swapped(): void
    {
        $reservation = $this->addReservation();

        $this->update($reservation, 'visited');
        $this->update($reservation, 'no_show');
        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_NO_SHOW, $reservation->status);
        $this->assertNull($reservation->visited_at);

        $this->update($reservation, 'visited');
        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_VISITED, $reservation->status);
        $this->assertNotNull($reservation->visited_at);
    }

    public function test_redirect_keeps_list_conditions_and_ignores_invalid_values(): void
    {
        $reservation = $this->addReservation();

        $this->update($reservation, 'visited', extra: ['period' => 'past', 'match' => '5', 'page' => '2'])
            ->assertRedirect(route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'past', 'match' => 5, 'page' => 2]));

        $this->update($reservation, 'no_show', extra: ['period' => 'http://evil.example', 'match' => 'abc', 'page' => '-1'])
            ->assertRedirect(route('owner.reservations.index', ['shop' => $this->shop->id, 'period' => 'upcoming']));
    }

    public function test_message_is_shown_on_list_after_redirect(): void
    {
        $reservation = $this->addReservation();

        $this->actingAs($this->owner)->followingRedirects()
            ->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'visited', 'period' => 'past'])
            ->assertOk()
            ->assertSee("予約 {$reservation->reservation_code} を来店済みにしました。")
            ->assertSee('来店なしに変更');
    }

    public function test_exactly_at_kickoff_is_allowed_and_one_second_before_is_not(): void
    {
        $reservation = $this->addReservation(kickoffAt: '2026-10-31 12:00:00');
        $this->update($reservation, 'visited')->assertSessionHas('success');
        $this->assertSame(Reservation::STATUS_VISITED, $reservation->refresh()->status);

        $before = $this->addReservation(kickoffAt: '2026-10-31 12:00:01');
        $this->update($before, 'visited')->assertSessionHas('error', ReservationService::NOT_VISIT_STATUS_CHANGEABLE_MESSAGE);
        $this->assertSame(Reservation::STATUS_CONFIRMED, $before->refresh()->status);
    }

    public function test_cannot_change_cancelled_reservation(): void
    {
        $reservation = $this->addReservation(state: ['status' => Reservation::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $this->update($reservation, 'visited')->assertSessionHas('error', ReservationService::NOT_VISIT_STATUS_CHANGEABLE_MESSAGE);
        $this->assertSame(Reservation::STATUS_CANCELLED, $reservation->refresh()->status);
    }

    public function test_cannot_change_to_same_status(): void
    {
        $visited = $this->addReservation(state: ['status' => Reservation::STATUS_VISITED, 'visited_at' => now()->subMinute()]);
        $this->update($visited, 'visited')->assertSessionHas('error');

        $noShow = $this->addReservation(state: ['status' => Reservation::STATUS_NO_SHOW]);
        $this->update($noShow, 'no_show')->assertSessionHas('error');
        $this->assertSame(Reservation::STATUS_NO_SHOW, $noShow->refresh()->status);
    }

    #[DataProvider('invalidStatusProvider')]
    public function test_invalid_status_is_rejected(?string $status): void
    {
        $reservation = $this->addReservation();

        $this->actingAs($this->owner)
            ->patch(route('owner.reservations.visit-status', $reservation), $status === null ? [] : ['status' => $status])
            ->assertSessionHasErrors('status');

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->refresh()->status);
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function invalidStatusProvider(): array
    {
        return [
            'confirmed' => ['confirmed'],
            'cancelled' => ['cancelled'],
            'invalid' => ['foo'],
            'empty' => [''],
            'missing' => [null],
        ];
    }

    public function test_double_submit_fails_the_second_time_and_keeps_first_result(): void
    {
        $reservation = $this->addReservation();

        $this->update($reservation, 'visited')->assertSessionHas('success');
        $firstVisitedAt = $reservation->refresh()->visited_at;

        $this->travel(5)->minutes();
        $this->update($reservation, 'visited')->assertSessionHas('error');

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_VISITED, $reservation->status);
        $this->assertTrue($reservation->visited_at->equalTo($firstVisitedAt));
    }

    public function test_service_rechecks_latest_state_after_cancel(): void
    {
        // キックオフ後の予約。古い（confirmed の）モデルを持ったまま、別の操作で DB 上がキャンセルに変わったケース
        $reservation = $this->addReservation();
        $stale = Reservation::query()->findOrFail($reservation->id);
        $this->assertTrue($stale->load('broadcastSeatType.broadcast.footballMatch')->canChangeVisitStatusTo('visited'));

        Reservation::query()->whereKey($reservation->id)->update([
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        try {
            app(ReservationService::class)->updateVisitStatus($stale, 'visited');
            $this->fail('ReservationException が投げられるはずです。');
        } catch (ReservationException) {
            // 期待どおり
        }

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_CANCELLED, $reservation->status);
        $this->assertNull($reservation->visited_at);
    }

    public function test_other_shops_reservation_is_404_and_unchanged(): void
    {
        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id, 'status' => 'published']);
        $reservation = $this->addReservation($otherShop);

        $this->update($reservation, 'visited')->assertNotFound();
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->refresh()->status);
    }

    public function test_deleted_shops_reservation_is_404(): void
    {
        $reservation = $this->addReservation();
        $this->shop->delete();

        $this->update($reservation, 'visited')->assertNotFound();
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->refresh()->status);
    }

    public function test_user_and_admin_get_403(): void
    {
        $reservation = $this->addReservation();

        $this->update($reservation, 'visited', User::factory()->create())->assertForbidden();

        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $this->update($reservation, 'visited', $admin)->assertForbidden();

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->refresh()->status);
    }

    public function test_policy_denies_non_shop_owner_even_for_own_shop(): void
    {
        $reservation = $this->addReservation();
        $reservation->load('broadcastSeatType.broadcast.shop');
        $this->owner->role = 'user';

        $this->assertTrue((new ReservationPolicy)->updateVisitStatus($this->owner, $reservation)->denied());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $reservation = $this->addReservation();

        $this->patch(route('owner.reservations.visit-status', $reservation), ['status' => 'visited'])->assertRedirect(route('login'));
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->refresh()->status);
    }

    public function test_missing_reservation_is_404(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('owner.reservations.visit-status', 999999), ['status' => 'visited'])
            ->assertNotFound();
    }

    public function test_get_is_not_allowed(): void
    {
        $reservation = $this->addReservation();

        $this->actingAs($this->owner)->get("/owner/reservations/{$reservation->id}/visit-status")->assertStatus(405);
    }

    public function test_list_shows_buttons_per_status(): void
    {
        $confirmed = $this->addReservation(state: ['reservation_code' => 'CONFIRM1']);
        $visited = $this->addReservation(state: ['reservation_code' => 'VISITED1', 'status' => Reservation::STATUS_VISITED]);
        $noShow = $this->addReservation(state: ['reservation_code' => 'NOSHOW11', 'status' => Reservation::STATUS_NO_SHOW]);

        $html = $this->actingAs($this->owner)->get(route('owner.reservations.index', ['period' => 'past']))->assertOk()->getContent();

        $this->assertStringContainsString(route('owner.reservations.visit-status', $confirmed), $html);
        $this->assertStringContainsString(route('owner.reservations.visit-status', $visited), $html);
        $this->assertStringContainsString(route('owner.reservations.visit-status', $noShow), $html);
        $this->assertStringContainsString('data-confirm=', $html);
        // ボタンの文言（confirmed は2つ、visited / no_show は1つずつ）
        $this->assertSame(1, substr_count($html, '>来店済み</button>'));
        $this->assertSame(1, substr_count($html, '>来店なし</button>'));
        $this->assertSame(1, substr_count($html, '>来店なしに変更</button>'));
        $this->assertSame(1, substr_count($html, '>来店済みに変更</button>'));
    }

    public function test_list_shows_no_button_before_kickoff_or_for_cancelled(): void
    {
        $this->addReservation(kickoffAt: '2026-11-05 12:00:00');
        $this->addReservation(state: ['status' => Reservation::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $this->actingAs($this->owner)->get(route('owner.reservations.index', ['period' => 'past']))
            ->assertOk()->assertDontSee('visit-status')->assertDontSee('data-confirm', false);
        $this->actingAs($this->owner)->get(route('owner.reservations.index'))
            ->assertOk()->assertDontSee('visit-status');
    }
}
