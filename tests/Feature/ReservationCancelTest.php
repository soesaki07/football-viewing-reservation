<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Services\ReservationService;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class ReservationCancelTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $user;

    private Shop $shop;

    private Broadcast $broadcast;

    private BroadcastSeatType $broadcastSeatType;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        // 期限（キックオフ）の境目を扱うので現在時刻を固定する
        $this->travelTo('2026-10-31 12:00:00');

        $this->user = User::factory()->create();
        $this->shop = Shop::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => Shop::STATUS_PUBLISHED,
        ]);
        $this->broadcast = Broadcast::factory()->forMatch($this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']))->create([
            'shop_id' => $this->shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
            'reservation_start_at' => now()->subDay(),
            'reservation_end_at' => now()->addDay(),
        ]);
        $seatType = SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => 'ソファ席', 'default_capacity' => 10, 'default_price' => 3000]);
        $this->broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $this->broadcast->id]);

        $this->reservation = Reservation::factory()->create([
            'broadcast_seat_type_id' => $this->broadcastSeatType->id,
            'user_id' => $this->user->id,
            'number_of_people' => 4,
            'unit_price' => 3000,
            'total_price' => 12000,
        ]);
    }

    private function cancel(?Reservation $reservation = null, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->user)
            ->patch(route('reservations.cancel', $reservation ?? $this->reservation));
    }

    public function test_owner_can_cancel_before_kickoff(): void
    {
        $this->cancel()
            ->assertRedirect(route('reservations.show', $this->reservation))
            ->assertSessionHas('success', '予約をキャンセルしました。');

        $this->reservation->refresh();
        $this->assertSame(Reservation::STATUS_CANCELLED, $this->reservation->status);
        $this->assertNotNull($this->reservation->cancelled_at);
        $this->assertTrue($this->reservation->cancelled_at->equalTo(now()));
        // 人数・金額は変えない
        $this->assertSame(4, $this->reservation->number_of_people);
        $this->assertSame(12000, $this->reservation->total_price);
    }

    public function test_detail_shows_message_and_cancelled_status_after_cancel(): void
    {
        $this->actingAs($this->user)->followingRedirects()
            ->patch(route('reservations.cancel', $this->reservation))
            ->assertOk()
            ->assertSee('予約をキャンセルしました。')
            ->assertSee('キャンセル済み')
            ->assertDontSee('この予約をキャンセルする');
    }

    public function test_remaining_seats_are_restored_and_match_can_be_reserved_again(): void
    {
        $this->assertSame(6, $this->broadcastSeatType->remainingSeats());

        $this->cancel();

        $this->assertSame(10, $this->broadcastSeatType->remainingSeats());

        $new = app(ReservationService::class)->reserve($this->user, $this->broadcast, $this->broadcastSeatType->id, 2, null);
        $this->assertSame(Reservation::STATUS_CONFIRMED, $new->status);
        $this->assertSame(8, $this->broadcastSeatType->remainingSeats());
    }

    public function test_cancelled_reservation_is_shown_in_mypage(): void
    {
        $this->cancel();

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('キャンセル済み');
    }

    public function test_can_cancel_one_second_before_kickoff(): void
    {
        $this->travelTo('2026-11-05 11:59:59');

        $this->cancel()->assertSessionHas('success');
        $this->assertSame(Reservation::STATUS_CANCELLED, $this->reservation->refresh()->status);
    }

    public function test_cannot_cancel_at_kickoff(): void
    {
        $this->travelTo('2026-11-05 12:00:00');

        $this->cancel()
            ->assertRedirect(route('reservations.show', $this->reservation))
            ->assertSessionHas('error', 'この予約はキャンセルできません。');
        $this->assertSame(Reservation::STATUS_CONFIRMED, $this->reservation->refresh()->status);
        $this->assertNull($this->reservation->cancelled_at);
    }

    public function test_cannot_cancel_after_kickoff(): void
    {
        $this->travelTo('2026-11-05 12:00:01');

        $this->cancel()->assertSessionHas('error', 'この予約はキャンセルできません。');
        $this->assertSame(Reservation::STATUS_CONFIRMED, $this->reservation->refresh()->status);
    }

    public function test_other_users_reservation_is_not_found_and_unchanged(): void
    {
        $this->cancel(user: User::factory()->create())->assertNotFound();

        $this->assertSame(Reservation::STATUS_CONFIRMED, $this->reservation->refresh()->status);
        $this->assertNull($this->reservation->cancelled_at);
    }

    public function test_missing_reservation_is_not_found(): void
    {
        $this->actingAs($this->user)->patch('/reservations/999999/cancel')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->patch(route('reservations.cancel', $this->reservation))->assertRedirect(route('login'));
        $this->assertSame(Reservation::STATUS_CONFIRMED, $this->reservation->refresh()->status);
    }

    public function test_get_request_is_not_allowed(): void
    {
        $this->actingAs($this->user)->get(route('reservations.cancel', $this->reservation))->assertStatus(405);
        $this->assertSame(Reservation::STATUS_CONFIRMED, $this->reservation->refresh()->status);
    }

    public function test_cannot_cancel_twice(): void
    {
        $this->cancel();
        $cancelledAt = $this->reservation->refresh()->cancelled_at;

        $this->travelTo('2026-10-31 13:00:00');
        $this->cancel()->assertSessionHas('error', 'この予約はキャンセルできません。');

        // 最初のキャンセル時刻のまま変わらない
        $this->assertTrue($this->reservation->refresh()->cancelled_at->equalTo($cancelledAt));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonConfirmedStatuses(): array
    {
        return [
            'cancelled' => [Reservation::STATUS_CANCELLED],
            'visited' => [Reservation::STATUS_VISITED],
            'no_show' => [Reservation::STATUS_NO_SHOW],
        ];
    }

    #[DataProvider('nonConfirmedStatuses')]
    public function test_cannot_cancel_non_confirmed_reservation(string $status): void
    {
        $this->reservation->update(['status' => $status]);

        $this->cancel()->assertSessionHas('error', 'この予約はキャンセルできません。');
        $this->assertSame($status, $this->reservation->refresh()->status);
        $this->assertNull($this->reservation->cancelled_at);
    }

    public function test_can_cancel_when_shop_is_deleted(): void
    {
        $this->shop->delete();

        $this->cancel()->assertSessionHas('success');
        $this->assertSame(Reservation::STATUS_CANCELLED, $this->reservation->refresh()->status);
    }

    public function test_can_cancel_when_shop_and_broadcast_are_unpublished(): void
    {
        $this->shop->update(['status' => 'draft']);
        $this->broadcast->update(['status' => 'draft', 'reservation_end_at' => now()->subHour()]);

        $this->cancel()->assertSessionHas('success');
        $this->assertSame(Reservation::STATUS_CANCELLED, $this->reservation->refresh()->status);
    }

    public function test_cancel_button_is_shown_with_confirm_dialog(): void
    {
        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertSee('この予約をキャンセルする')
            ->assertSee(route('reservations.cancel', $this->reservation), false)
            ->assertSee('data-confirm="この予約をキャンセルしますか？"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_cancel_button_is_hidden_after_kickoff(): void
    {
        $this->travelTo('2026-11-05 12:00:00');

        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertDontSee('この予約をキャンセルする');
    }

    #[DataProvider('nonConfirmedStatuses')]
    public function test_cancel_button_is_hidden_for_non_confirmed_reservation(string $status): void
    {
        $this->reservation->update(['status' => $status]);

        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertDontSee('この予約をキャンセルする');
    }

    public function test_error_message_is_shown_on_detail_and_escaped(): void
    {
        $this->reservation->update(['status' => Reservation::STATUS_VISITED]);

        $this->actingAs($this->user)->followingRedirects()
            ->patch(route('reservations.cancel', $this->reservation))
            ->assertOk()
            ->assertSee('この予約はキャンセルできません。');
    }

    public function test_policy_cancel_allows_owner_and_hides_others_as_not_found(): void
    {
        $policy = new ReservationPolicy;

        $this->assertTrue($policy->cancel($this->user, $this->reservation)->allowed());

        $response = $policy->cancel(User::factory()->create(), $this->reservation);
        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    public function test_cancel_is_denied_and_button_hidden_when_only_view_is_allowed(): void
    {
        // view は許可するが cancel は許可しない Policy に差し替える（view を広げてもキャンセル権限が増えないことの確認）
        $policy = new class extends ReservationPolicy
        {
            public function cancel(User $user, Reservation $reservation): Response
            {
                return Response::denyAsNotFound();
            }
        };
        $this->app->instance($policy::class, $policy);
        Gate::policy(Reservation::class, $policy::class);

        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertDontSee('この予約をキャンセルする');

        $this->cancel()->assertNotFound();
        $this->assertSame(Reservation::STATUS_CONFIRMED, $this->reservation->fresh()->status);
    }

    public function test_cancel_loads_relations_used_by_cancellable_check(): void
    {
        // 関連を読み込んでいない予約を渡しても、cancel() がロック後に明示的に読み込む
        $reservation = Reservation::findOrFail($this->reservation->id);
        $this->assertFalse($reservation->relationLoaded('broadcastSeatType'));

        // 1件の取得では遅延読み込みの禁止が付かないため、取得したモデルに付けて、遅延読み込みなら例外になるようにする
        Model::preventLazyLoading();
        Reservation::retrieved(fn (Reservation $model) => $model->preventsLazyLoading = true);

        try {
            $cancelled = app(ReservationService::class)->cancel($reservation);
        } finally {
            Model::preventLazyLoading(false);
            Reservation::flushEventListeners();
        }

        $this->assertTrue($cancelled->relationLoaded('broadcastSeatType'));
        $this->assertTrue($cancelled->broadcastSeatType->relationLoaded('broadcast'));
        $this->assertTrue($cancelled->broadcastSeatType->broadcast->relationLoaded('footballMatch'));
    }
}
