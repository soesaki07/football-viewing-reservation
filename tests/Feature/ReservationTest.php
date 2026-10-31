<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $user;

    private FootballMatch $match;

    private Broadcast $broadcast;

    private BroadcastSeatType $seatType;

    protected function setUp(): void
    {
        parent::setUp();

        // 日時を扱うテストなので現在時刻を固定する
        $this->travelTo('2026-10-31 12:00:00');

        $this->user = User::factory()->create();
        $this->match = $this->createFootballMatch();
        $this->broadcast = $this->createBroadcast($this->match);
        $this->seatType = $this->addSeatType($this->broadcast, 10, 3000);
    }

    /**
     * @param  array<string, mixed>  $shopOverrides
     * @param  array<string, mixed>  $broadcastOverrides
     */
    private function createBroadcast(FootballMatch $match, array $shopOverrides = [], array $broadcastOverrides = []): Broadcast
    {
        $shop = Shop::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'status' => Shop::STATUS_PUBLISHED,
        ], $shopOverrides));

        return Broadcast::factory()->forMatch($match)->create(array_merge([
            'shop_id' => $shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
            'reservation_start_at' => now()->subDay(),
            'reservation_end_at' => now()->addDay(),
        ], $broadcastOverrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addSeatType(Broadcast $broadcast, int $capacity, int $price, array $overrides = [], bool $seatTypeActive = true): BroadcastSeatType
    {
        $seatType = SeatType::factory()->create([
            'shop_id' => $broadcast->shop_id,
            'name' => 'テーブル席'.SeatType::count(),
            'default_capacity' => $capacity,
            'default_price' => $price,
            'is_active' => $seatTypeActive,
        ]);

        return BroadcastSeatType::factory()->forSeatType($seatType)->create(array_merge([
            'broadcast_id' => $broadcast->id,
        ], $overrides));
    }

    private function addReservation(BroadcastSeatType $seatType, int $people, ?User $user = null, bool $cancelled = false): Reservation
    {
        $factory = Reservation::factory()->state([
            'broadcast_seat_type_id' => $seatType->id,
            'number_of_people' => $people,
            'user_id' => ($user ?? User::factory()->create())->id,
        ]);

        return ($cancelled ? $factory->cancelled() : $factory)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'broadcast_seat_type_id' => $this->seatType->id,
            'number_of_people' => 2,
            'customer_note' => '窓際希望',
        ], $overrides);
    }

    private function confirmAndStore(?Broadcast $broadcast = null, array $input = [])
    {
        $broadcast ??= $this->broadcast;
        $this->actingAs($this->user)->post(route('reservations.confirm', $broadcast), $this->input($input))->assertOk();

        return $this->actingAs($this->user)->post(route('reservations.store', $broadcast));
    }

    // ---- 正常系 ----

    public function test_user_can_reserve_through_input_confirm_store_complete(): void
    {
        $this->actingAs($this->user)->get(route('reservations.create', $this->broadcast))
            ->assertOk()
            ->assertSee('3,000円')
            ->assertSee('残り10席');

        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input())
            ->assertOk()
            ->assertSee('6,000円')
            ->assertSee('2026/11/30 21:00') // 30日後のキックオフ（JST 表示）
            ->assertSee('窓際希望');

        $response = $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast));

        $reservation = Reservation::sole();
        $response->assertRedirect(route('reservations.complete', $reservation));
        $this->assertSame($this->user->id, $reservation->user_id);
        $this->assertSame($this->seatType->id, $reservation->broadcast_seat_type_id);
        $this->assertSame(2, $reservation->number_of_people);
        $this->assertSame(3000, $reservation->unit_price);
        $this->assertSame(6000, $reservation->total_price);
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->status);
        $this->assertSame('2026-10-31 12:00:00', $reservation->reserved_at->format('Y-m-d H:i:s'));
        $this->assertSame('窓際希望', $reservation->customer_note);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $reservation->reservation_code);
        // 確定後は入力値をセッションから消す
        $response->assertSessionMissing('reservation_input.'.$this->broadcast->id);

        $this->actingAs($this->user)->get(route('reservations.complete', $reservation))
            ->assertOk()
            ->assertSee($reservation->reservation_code)
            ->assertSee('6,000円')
            ->assertSee('2人');
    }

    public function test_price_at_store_time_is_saved(): void
    {
        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input())->assertOk();
        // 確認画面のあとに価格が変わった
        $this->seatType->update(['price' => 3500]);

        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast));

        $reservation = Reservation::sole();
        $this->assertSame(3500, $reservation->unit_price);
        $this->assertSame(7000, $reservation->total_price);
    }

    public function test_store_ignores_request_values(): void
    {
        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input())->assertOk();

        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast), [
            'number_of_people' => 5,
            'total_price' => 1,
            'unit_price' => 1,
        ]);

        $reservation = Reservation::sole();
        $this->assertSame(2, $reservation->number_of_people);
        $this->assertSame(6000, $reservation->total_price);
    }

    public function test_note_is_optional(): void
    {
        $this->confirmAndStore(input: ['customer_note' => null]);

        $this->assertNull(Reservation::sole()->customer_note);
    }

    public function test_any_role_can_reserve(): void
    {
        $this->user->forceFill(['role' => 'shop_owner'])->save();

        $this->confirmAndStore()->assertRedirect();

        $this->assertSame(1, Reservation::count());
    }

    // ---- 入力の異常系 ----

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reservations.create', $this->broadcast))->assertRedirect(route('login'));
        $this->post(route('reservations.confirm', $this->broadcast), $this->input())->assertRedirect(route('login'));
        $this->post(route('reservations.store', $this->broadcast))->assertRedirect(route('login'));
        $this->assertSame(0, Reservation::count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            '人数が0' => [['number_of_people' => 0], 'number_of_people'],
            '人数が負' => [['number_of_people' => -1], 'number_of_people'],
            '人数が数字以外' => [['number_of_people' => 'abc'], 'number_of_people'],
            '人数が小数' => [['number_of_people' => '1.5'], 'number_of_people'],
            '人数が未入力' => [['number_of_people' => ''], 'number_of_people'],
            '人数が1回の上限超え' => [['number_of_people' => 5], 'number_of_people'],
            '座席種別が未指定' => [['broadcast_seat_type_id' => ''], 'broadcast_seat_type_id'],
            '座席種別が存在しない' => [['broadcast_seat_type_id' => 999999], 'broadcast_seat_type_id'],
            '備考が201文字' => [['customer_note' => str_repeat('あ', 201)], 'customer_note'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_confirm_rejects_invalid_input(array $override, string $field): void
    {
        $response = $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input($override));

        $response->assertSessionHasErrors($field);
        $response->assertSessionMissing('reservation_input.'.$this->broadcast->id);
    }

    public function test_note_of_200_characters_is_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(route('reservations.confirm', $this->broadcast), $this->input(['customer_note' => str_repeat('あ', 200)]))
            ->assertOk();
    }

    public function test_confirm_rejects_people_over_remaining_seats(): void
    {
        $this->addReservation($this->seatType, 9);

        $this->actingAs($this->user)
            ->post(route('reservations.confirm', $this->broadcast), $this->input(['number_of_people' => 2]))
            ->assertSessionHasErrors('number_of_people');
    }

    public function test_confirm_rejects_sold_out_seat_type(): void
    {
        $this->addReservation($this->seatType, 10);

        $this->actingAs($this->user)
            ->post(route('reservations.confirm', $this->broadcast), $this->input(['number_of_people' => 1]))
            ->assertSessionHasErrors('number_of_people');
    }

    public function test_confirm_rejects_inactive_seat_types(): void
    {
        $inactiveBroadcastSeatType = $this->addSeatType($this->broadcast, 10, 3000, ['is_active' => false]);
        $inactiveSeatType = $this->addSeatType($this->broadcast, 10, 3000, [], false);

        foreach ([$inactiveBroadcastSeatType, $inactiveSeatType] as $seatType) {
            $this->actingAs($this->user)
                ->post(route('reservations.confirm', $this->broadcast), $this->input(['broadcast_seat_type_id' => $seatType->id]))
                ->assertSessionHasErrors('broadcast_seat_type_id');
        }
    }

    public function test_confirm_rejects_seat_type_of_another_broadcast(): void
    {
        $otherBroadcast = $this->createBroadcast($this->createFootballMatch());
        $otherSeatType = $this->addSeatType($otherBroadcast, 10, 3000);

        $this->actingAs($this->user)
            ->post(route('reservations.confirm', $this->broadcast), $this->input(['broadcast_seat_type_id' => $otherSeatType->id]))
            ->assertSessionHasErrors('broadcast_seat_type_id');
    }

    // ---- 予約できない放映 ----

    /**
     * @return array<string, array{0: callable(self): Broadcast}>
     */
    public static function unreservableBroadcasts(): array
    {
        return [
            '受付前' => [fn (self $t) => $t->createBroadcast($t->createFootballMatch(), [], ['reservation_start_at' => now()->addMinute()])],
            '受付終了' => [fn (self $t) => $t->createBroadcast($t->createFootballMatch(), [], ['reservation_end_at' => now()->subMinute()])],
            '放映が非公開' => [fn (self $t) => $t->createBroadcast($t->createFootballMatch(), [], ['status' => 'draft'])],
            '店舗が非公開' => [fn (self $t) => $t->createBroadcast($t->createFootballMatch(), ['status' => 'draft'])],
            '店舗が削除済み' => [fn (self $t) => $t->createBroadcast($t->createFootballMatch(), ['deleted_at' => now()])],
            '試合が終了' => [fn (self $t) => $t->createBroadcast($t->createFootballMatch(['status' => 'FINISHED']))],
            'キックオフから3時間経過' => [fn (self $t) => $t->createBroadcast($t->createFootballMatch(['kickoff_at' => now()->subHours(3)]))],
        ];
    }

    /**
     * @param  callable(self): Broadcast  $factory
     */
    #[DataProvider('unreservableBroadcasts')]
    public function test_unreservable_broadcast_is_rejected_everywhere(callable $factory): void
    {
        $broadcast = $factory($this);
        $seatType = $this->addSeatType($broadcast, 10, 3000);
        $input = $this->input(['broadcast_seat_type_id' => $seatType->id]);

        $this->actingAs($this->user)->get(route('reservations.create', $broadcast))
            ->assertRedirect(route('matches.show', $broadcast->football_match_id));
        $this->actingAs($this->user)->post(route('reservations.confirm', $broadcast), $input)
            ->assertRedirect(route('matches.show', $broadcast->football_match_id));

        // 確認画面を通らずセッションに入力値を直接入れても、確定時に拒否される
        $this->actingAs($this->user)
            ->withSession(['reservation_input.'.$broadcast->id => $input])
            ->post(route('reservations.store', $broadcast))
            ->assertRedirect(route('reservations.create', $broadcast))
            ->assertSessionHas('error');
        $this->assertSame(0, Reservation::count());
    }

    public function test_reservation_period_boundaries_are_inclusive(): void
    {
        $startNow = $this->createBroadcast($this->match, [], ['reservation_start_at' => now(), 'reservation_end_at' => null]);
        $endNow = $this->createBroadcast($this->match, [], ['reservation_start_at' => null, 'reservation_end_at' => now()]);

        $this->assertTrue($startNow->isReservable());
        $this->assertTrue($endNow->isReservable());
    }

    // ---- 確定の異常系 ----

    public function test_store_without_session_input_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast))
            ->assertRedirect(route('reservations.create', $this->broadcast))
            ->assertSessionHas('error');

        $this->assertSame(0, Reservation::count());
    }

    public function test_store_rechecks_remaining_seats(): void
    {
        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input(['number_of_people' => 2]))->assertOk();
        // 確認画面のあとに、ほかの人が席を埋めた
        $this->addReservation($this->seatType, 9);

        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast))
            ->assertRedirect(route('reservations.create', $this->broadcast))
            ->assertSessionHas('error');

        $this->assertSame(1, Reservation::count());
    }

    public function test_store_rechecks_period_and_match_status(): void
    {
        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input())->assertOk();
        // 確認画面のあとに受付が終了した
        $this->broadcast->update(['reservation_end_at' => now()->subMinute()]);

        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast))->assertSessionHas('error');

        $this->broadcast->update(['reservation_end_at' => now()->addDay()]);
        $this->match->update(['status' => 'FINISHED']);

        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast))->assertSessionHas('error');
        $this->assertSame(0, Reservation::count());
    }

    public function test_store_rechecks_seat_type_is_active(): void
    {
        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input())->assertOk();
        $this->seatType->update(['is_active' => false]);

        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast))->assertSessionHas('error');

        $this->assertSame(0, Reservation::count());
    }

    public function test_last_seat_can_be_taken_only_once(): void
    {
        $this->addReservation($this->seatType, 9);
        $other = User::factory()->create();

        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input(['number_of_people' => 1]))->assertOk();
        $this->actingAs($other)->post(route('reservations.confirm', $this->broadcast), $this->input(['number_of_people' => 1]))->assertOk();

        $this->actingAs($this->user)->post(route('reservations.store', $this->broadcast))
            ->assertRedirect(route('reservations.complete', Reservation::where('user_id', $this->user->id)->sole()));
        $this->actingAs($other)->post(route('reservations.store', $this->broadcast))
            ->assertRedirect(route('reservations.create', $this->broadcast))
            ->assertSessionHas('error');

        $this->assertSame(0, Reservation::where('user_id', $other->id)->count());
        $this->assertSame(10, (int) Reservation::confirmed()->sum('number_of_people'));
    }

    // ---- 二重予約 ----

    public function test_second_reservation_for_same_broadcast_is_rejected(): void
    {
        $this->confirmAndStore()->assertRedirect(route('reservations.complete', Reservation::sole()));

        // 確認画面の時点で試合詳細に戻される
        $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), $this->input())
            ->assertRedirect(route('matches.show', $this->match->id))
            ->assertSessionHas('error', 'この試合はすでに予約済みです。');

        // 確認画面のあとで予約済みになった場合（別タブでの二重送信など）は、確定時に拒否される
        $this->actingAs($this->user)
            ->withSession(['reservation_input.'.$this->broadcast->id => $this->input()])
            ->post(route('reservations.store', $this->broadcast))
            ->assertRedirect(route('reservations.create', $this->broadcast))
            ->assertSessionHas('error', 'この試合はすでに予約済みです。');

        $this->assertSame(1, Reservation::count());
    }

    /**
     * 同じ試合を別の店舗で予約済みのユーザーと、その試合の別店舗の放映・座席種別を用意する。
     *
     * @return array{0: Broadcast, 1: BroadcastSeatType}
     */
    private function reservedMatchWithOtherShop(): array
    {
        $this->addReservation($this->seatType, 1, $this->user);
        $otherBroadcast = $this->createBroadcast($this->match);

        return [$otherBroadcast, $this->addSeatType($otherBroadcast, 10, 3000)];
    }

    public function test_second_reservation_for_same_match_at_other_shop_is_rejected_at_store(): void
    {
        [$otherBroadcast, $otherSeatType] = $this->reservedMatchWithOtherShop();
        $input = $this->input(['broadcast_seat_type_id' => $otherSeatType->id]);

        // 確認画面を通らずセッションに入力値を直接入れても、確定時に拒否される
        $this->actingAs($this->user)
            ->withSession(['reservation_input.'.$otherBroadcast->id => $input])
            ->post(route('reservations.store', $otherBroadcast))
            ->assertRedirect(route('reservations.create', $otherBroadcast))
            ->assertSessionHas('error', 'この試合はすでに予約済みです。');

        $this->assertSame(1, Reservation::count());
    }

    public function test_second_reservation_for_same_match_at_other_shop_is_rejected_at_create_and_confirm(): void
    {
        [$otherBroadcast, $otherSeatType] = $this->reservedMatchWithOtherShop();

        $this->actingAs($this->user)->get(route('reservations.create', $otherBroadcast))
            ->assertRedirect(route('matches.show', $this->match->id))
            ->assertSessionHas('error', 'この試合はすでに予約済みです。');

        $this->actingAs($this->user)
            ->post(route('reservations.confirm', $otherBroadcast), $this->input(['broadcast_seat_type_id' => $otherSeatType->id]))
            ->assertRedirect(route('matches.show', $this->match->id))
            ->assertSessionHas('error', 'この試合はすでに予約済みです。')
            ->assertSessionMissing('reservation_input.'.$otherBroadcast->id);

        $this->actingAs($this->user)->followingRedirects()->get(route('reservations.create', $otherBroadcast))
            ->assertSee('この試合はすでに予約済みです。');
    }

    public function test_user_can_reserve_same_match_at_other_shop_after_cancellation(): void
    {
        $this->addReservation($this->seatType, 1, $this->user, cancelled: true);
        $otherBroadcast = $this->createBroadcast($this->match);
        $otherSeatType = $this->addSeatType($otherBroadcast, 10, 3000);

        $this->confirmAndStore($otherBroadcast, ['broadcast_seat_type_id' => $otherSeatType->id])->assertRedirect();

        $this->assertSame(1, Reservation::confirmed()->count());
    }

    public function test_visited_and_no_show_reservations_do_not_block_same_match(): void
    {
        foreach ([Reservation::STATUS_VISITED, Reservation::STATUS_NO_SHOW] as $status) {
            $this->addReservation($this->seatType, 1, $this->user)->update(['status' => $status]);
        }
        $service = new ReservationService;

        $this->assertFalse($service->hasReservedMatch($this->user, $this->match->id));
    }

    public function test_other_users_reservation_does_not_block_same_match(): void
    {
        [$otherBroadcast, $otherSeatType] = $this->reservedMatchWithOtherShop();

        $this->actingAs(User::factory()->create())->get(route('reservations.create', $otherBroadcast))->assertOk();
        $this->assertSame(1, Reservation::count());
    }

    public function test_second_reservation_with_other_seat_type_is_rejected(): void
    {
        $other = $this->addSeatType($this->broadcast, 10, 5000);
        $this->addReservation($this->seatType, 1, $this->user);

        $this->actingAs($this->user)
            ->withSession(['reservation_input.'.$this->broadcast->id => $this->input(['broadcast_seat_type_id' => $other->id])])
            ->post(route('reservations.store', $this->broadcast))
            ->assertSessionHas('error', 'この試合はすでに予約済みです。');

        $this->assertSame(1, Reservation::count());
    }

    public function test_user_can_reserve_again_after_cancellation(): void
    {
        $this->addReservation($this->seatType, 1, $this->user, cancelled: true);

        $this->confirmAndStore()->assertRedirect();

        $this->assertSame(1, Reservation::confirmed()->count());
    }

    public function test_user_can_reserve_other_broadcasts(): void
    {
        $this->addReservation($this->seatType, 1, $this->user);
        $otherBroadcast = $this->createBroadcast($this->createFootballMatch());
        $otherSeatType = $this->addSeatType($otherBroadcast, 10, 3000);

        $this->confirmAndStore($otherBroadcast, ['broadcast_seat_type_id' => $otherSeatType->id])->assertRedirect();

        $this->assertSame(2, Reservation::confirmed()->count());
    }

    // ---- 完了画面 ----

    public function test_complete_is_only_visible_to_owner(): void
    {
        $reservation = $this->addReservation($this->seatType, 2, $this->user);

        $this->actingAs(User::factory()->create())->get(route('reservations.complete', $reservation))->assertNotFound();
        $this->actingAs($this->user)->get(route('reservations.complete', $reservation))->assertOk();
    }

    public function test_complete_requires_login(): void
    {
        $reservation = $this->addReservation($this->seatType, 2, $this->user);

        $this->get(route('reservations.complete', $reservation))->assertRedirect(route('login'));
    }

    public function test_reloading_complete_does_not_create_reservation(): void
    {
        $this->confirmAndStore();
        $reservation = Reservation::sole();

        $this->actingAs($this->user)->get(route('reservations.complete', $reservation))->assertOk();
        $this->actingAs($this->user)->get(route('reservations.complete', $reservation))->assertOk();

        $this->assertSame(1, Reservation::count());
    }

    // ---- 予約コード ----

    public function test_reservation_code_format(): void
    {
        $service = new ReservationService;

        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $service->generateReservationCode());
        }
    }

    public function test_reservation_code_is_regenerated_when_duplicated(): void
    {
        $this->addReservation($this->seatType, 1)->update(['reservation_code' => 'AAAAAAAA']);
        $service = new class extends ReservationService
        {
            /** @var list<string> */
            public array $candidates = ['AAAAAAAA', 'AAAAAAAA', 'BBBBBBBB'];

            protected function randomCode(): string
            {
                return array_shift($this->candidates);
            }
        };

        $this->assertSame('BBBBBBBB', $service->generateReservationCode());
        $this->assertSame([], $service->candidates);
    }

    // ---- 試合詳細の「予約する」ボタン ----

    public function test_match_show_displays_reserve_button_when_reservable(): void
    {
        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertOk()
            ->assertSee('予約する')
            ->assertSee(route('reservations.create', $this->broadcast), false);
    }

    public function test_match_show_hides_reserve_button_when_sold_out(): void
    {
        $this->addReservation($this->seatType, 10);

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertOk()
            ->assertSee('満席')
            ->assertDontSee('予約する');
    }

    public function test_match_show_hides_reserve_button_outside_period(): void
    {
        $this->broadcast->update(['reservation_start_at' => now()->addHour()]);

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertSee('受付前')
            ->assertDontSee('予約する');

        $this->broadcast->update(['reservation_start_at' => now()->subDay(), 'reservation_end_at' => now()->subHour()]);

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertSee('受付終了')
            ->assertDontSee('予約する');
    }

    // ---- 試合詳細の「予約済み」 ----

    public function test_match_show_displays_reserved_badge_and_hides_reserve_button_at_other_shops(): void
    {
        [$otherBroadcast] = $this->reservedMatchWithOtherShop();
        $reservation = Reservation::sole();

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertOk()
            ->assertSee('予約済み')
            ->assertSee('予約内容を見る')
            ->assertSee(route('reservations.show', $reservation), false)
            ->assertDontSee('予約する')
            ->assertDontSee(route('reservations.create', $otherBroadcast), false);
    }

    public function test_match_show_prefers_reserved_badge_over_sold_out(): void
    {
        $reservation = $this->addReservation($this->seatType, 10, $this->user);

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertSee('予約済み')
            ->assertSee(route('reservations.show', $reservation), false);
    }

    public function test_match_show_ignores_cancelled_and_other_users_reservations(): void
    {
        $this->addReservation($this->seatType, 1, $this->user, cancelled: true);
        $this->addReservation($this->seatType, 1);

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertDontSee('予約済み')
            ->assertSee('予約する');
    }

    public function test_match_show_ignores_reservation_of_other_match(): void
    {
        $otherBroadcast = $this->createBroadcast($this->createFootballMatch());
        $this->addReservation($this->addSeatType($otherBroadcast, 10, 3000), 1, $this->user);

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertDontSee('予約済み')
            ->assertSee('予約する');
    }

    public function test_match_show_hides_reserve_button_when_reserved_shop_is_not_listed(): void
    {
        $this->addReservation($this->seatType, 1, $this->user);
        // 予約した店舗が非公開になり、一覧に出なくなった
        $this->broadcast->shop->update(['status' => 'draft']);
        $otherBroadcast = $this->createBroadcast($this->match);
        $this->addSeatType($otherBroadcast, 10, 3000);

        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))
            ->assertOk()
            ->assertDontSee('予約する')
            ->assertDontSee('予約済み');
    }

    public function test_match_show_query_count_does_not_depend_on_shop_count(): void
    {
        $this->addReservation($this->seatType, 1, $this->user);
        $this->actingAs($this->user)->get(route('matches.show', $this->match->id))->assertOk();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('matches.show', $this->match->id))->assertOk();
        $before = count(DB::getQueryLog());

        foreach (range(1, 3) as $i) {
            $this->addSeatType($this->createBroadcast($this->match), 10, 3000);
        }
        DB::flushQueryLog();
        $this->get(route('matches.show', $this->match->id))->assertOk();

        $this->assertSame($before, count(DB::getQueryLog()));
    }
}
