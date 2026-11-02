<?php

namespace Tests\Feature;

use App\Exceptions\BroadcastUpdateException;
use App\Exceptions\ReservationException;
use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use App\Policies\BroadcastPolicy;
use App\Services\BroadcastService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class OwnerBroadcastEditTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    private SeatType $counter;

    private SeatType $table;

    private FootballMatch $match;

    private Broadcast $broadcast;

    private BroadcastSeatType $counterSeat;

    protected function setUp(): void
    {
        parent::setUp();

        // 受付期間・キックオフの境目を扱うので現在時刻を固定する（UTC）
        $this->travelTo('2026-10-31 12:00:00');

        $this->owner = User::factory()->shopOwner()->create();
        $this->shop = Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーA', 'status' => 'published']);
        $this->counter = $this->addSeatType($this->shop, 'カウンター席', 10, 3000);
        $this->table = $this->addSeatType($this->shop, 'テーブル席', 20, 2000);
        // キックオフ 2026-11-05 12:00 UTC = 21:00 JST
        $this->match = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);

        // カウンター席だけで登録済みの公開中の放映（受付期間：2026-10-31 00:00〜11-05 12:00 UTC）
        $this->broadcast = $this->addBroadcast($this->shop, $this->match, Broadcast::STATUS_PUBLISHED);
        $this->counterSeat = $this->broadcast->broadcastSeatTypes()->sole();
    }

    private function addSeatType(Shop $shop, string $name, int $capacity, int $price, bool $isActive = true): SeatType
    {
        return SeatType::create([
            'shop_id' => $shop->id,
            'name' => $name,
            'default_capacity' => $capacity,
            'default_price' => $price,
            'is_active' => $isActive,
        ]);
    }

    private function addBroadcast(Shop $shop, FootballMatch $match, string $status, ?SeatType $seatType = null): Broadcast
    {
        $broadcast = Broadcast::factory()->forMatch($match)->create([
            'shop_id' => $shop->id,
            'status' => $status,
            'reservation_start_at' => '2026-10-31 00:00:00',
            'reservation_end_at' => '2026-11-05 12:00:00',
        ]);
        BroadcastSeatType::factory()->forSeatType($seatType ?? $this->counter)->create(['broadcast_id' => $broadcast->id]);

        return $broadcast;
    }

    private function reserve(BroadcastSeatType $seat, int $people, string $status = Reservation::STATUS_CONFIRMED): Reservation
    {
        return Reservation::factory()->create([
            'broadcast_seat_type_id' => $seat->id,
            'number_of_people' => $people,
            'unit_price' => $seat->price,
            'total_price' => $seat->price * $people,
            'status' => $status,
        ]);
    }

    /**
     * 編集の入力。$overrides で上書きできる（キーに null を指定すると、その項目を送らない）。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        $input = array_merge([
            'reservation_start_at' => '2026-10-31T21:00',
            'reservation_end_at' => '2026-11-06T00:00',
            'status' => Broadcast::STATUS_PUBLISHED,
            'seats' => [
                $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '5'],
            ],
        ], $overrides);

        return array_filter($input, fn ($value) => $value !== null);
    }

    private function editUrl(?Broadcast $broadcast = null): string
    {
        return route('owner.broadcasts.edit', $broadcast ?? $this->broadcast);
    }

    private function update(array $input, ?Broadcast $broadcast = null)
    {
        return $this->actingAs($this->owner)->put(route('owner.broadcasts.update', $broadcast ?? $this->broadcast), $input);
    }

    private function indexUrl(): string
    {
        return route('owner.broadcasts.index', ['shop' => $this->shop->id]);
    }

    /**
     * 更新前の状態から変わっていないこと。
     */
    private function assertBroadcastUnchanged(): void
    {
        $this->assertSame(Broadcast::STATUS_PUBLISHED, $this->broadcast->fresh()->status);
        $this->assertSame('2026-10-31 00:00:00', $this->broadcast->fresh()->reservation_start_at);
        $seat = $this->counterSeat->fresh();
        $this->assertSame([10, 3000, 4, 1], [$seat->capacity, $seat->price, $seat->max_people_per_reservation, (int) $seat->is_active]);
        $this->assertSame(1, BroadcastSeatType::query()->count());
    }

    // ---- 認可 ----

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->editUrl())->assertRedirect(route('login'));
        $this->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput())->assertRedirect(route('login'));
    }

    public function test_user_and_admin_get_403(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        foreach ([User::factory()->create(), $admin] as $user) {
            $this->actingAs($user)->get($this->editUrl())->assertForbidden();
            $this->actingAs($user)->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput())->assertForbidden();
        }
        $this->assertBroadcastUnchanged();
    }

    public function test_other_owners_broadcast_is_404(): void
    {
        $otherOwner = User::factory()->shopOwner()->create();

        $this->actingAs($otherOwner)->get($this->editUrl())->assertNotFound();
        $this->actingAs($otherOwner)->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput())->assertNotFound();
        // 入力が不正でも、検証より先に認可される
        $this->actingAs($otherOwner)->put(route('owner.broadcasts.update', $this->broadcast), ['seats' => 'abc'])->assertNotFound();
        $this->assertBroadcastUnchanged();
    }

    public function test_owners_other_shops_broadcast_is_editable_but_not_through_another_shop(): void
    {
        // 同じオーナーの別店舗の放映は、その店舗の座席種別でだけ更新できる
        $shopB = Shop::factory()->create(['user_id' => $this->owner->id]);
        $seatB = $this->addSeatType($shopB, 'B席', 5, 1000);
        $broadcastB = $this->addBroadcast($shopB, $this->match, Broadcast::STATUS_DRAFT, $seatB);

        $this->actingAs($this->owner)->get($this->editUrl($broadcastB))->assertOk();
        // 店舗Aの座席種別は、店舗Bの放映には足せない
        $this->update($this->validInput(), $broadcastB)->assertSessionHasErrors('seats');
        $this->assertSame(1, $broadcastB->broadcastSeatTypes()->count());
    }

    public function test_deleted_shops_broadcast_is_404(): void
    {
        $shop = Shop::factory()->create(['user_id' => $this->owner->id]);
        $seat = $this->addSeatType($shop, '席', 5, 1000);
        $broadcast = $this->addBroadcast($shop, $this->match, Broadcast::STATUS_PUBLISHED, $seat);
        $shop->delete();

        $this->actingAs($this->owner)->get($this->editUrl($broadcast))->assertNotFound();
        $this->update($this->validInput(), $broadcast)->assertNotFound();
        $this->assertSame(Broadcast::STATUS_PUBLISHED, $broadcast->fresh()->status);
    }

    public function test_missing_or_non_numeric_broadcast_is_404(): void
    {
        $this->actingAs($this->owner)->get('/owner/broadcasts/999999/edit')->assertNotFound();
        $this->actingAs($this->owner)->get('/owner/broadcasts/abc/edit')->assertNotFound();
        $this->actingAs($this->owner)->put('/owner/broadcasts/999999', $this->validInput())->assertNotFound();
        $this->actingAs($this->owner)->put('/owner/broadcasts/abc', $this->validInput())->assertNotFound();
    }

    public function test_policy_allows_only_the_owner_of_the_broadcasts_shop(): void
    {
        $policy = new BroadcastPolicy;
        $other = User::factory()->shopOwner()->create();
        $user = User::factory()->create();

        $this->assertTrue($policy->update($this->owner, $this->broadcast)->allowed());
        $this->assertSame(404, $policy->update($other, $this->broadcast)->status());
        // shop_owner 以外は通常の拒否（Gate が 403 にする）
        $this->assertFalse($policy->update($user, $this->broadcast)->allowed());
        $this->assertNull($policy->update($user, $this->broadcast)->status());
    }

    // ---- 編集画面 ----

    public function test_edit_shows_current_values_reserved_people_and_addable_seats(): void
    {
        $this->reserve($this->counterSeat, 2);
        $this->reserve($this->counterSeat, 1, Reservation::STATUS_VISITED);
        $this->reserve($this->counterSeat, 2, Reservation::STATUS_CANCELLED);
        $this->reserve($this->counterSeat, 2, Reservation::STATUS_NO_SHOW);
        BroadcastSeatType::factory()->forSeatType($this->table)->create(['broadcast_id' => $this->broadcast->id, 'is_active' => false]);
        // 店舗の有効な座席種別のうち、まだこの放映にないもの（無効な座席種別・他店の座席種別は出ない）
        $addable = $this->addSeatType($this->shop, '個室', 6, 5000);
        $this->addSeatType($this->shop, '無効席', 6, 5000, false);

        $response = $this->actingAs($this->owner)->get($this->editUrl())->assertOk();
        $html = $response->getContent();

        $response
            // 受付期間は JST で表示
            ->assertSee('value="2026-10-31T09:00"', false)
            ->assertSee('value="2026-11-05T21:00"', false)
            ->assertSee('Arsenal FC vs Chelsea FC')
            ->assertSee('予約済み（来店済みを含む）：3人')
            ->assertSee('name="seats['.$this->counter->id.'][capacity]"', false)
            // 席数の下限は、予約済みの人数
            ->assertSee('min="3" max="65535"', false)
            ->assertSee('（販売停止中）')
            ->assertSee('（追加できる座席）')
            ->assertSee('個室')
            ->assertDontSee('無効席');
        $this->assertMatchesRegularExpression('/<option value="published"\s+selected/', $html);
        // 販売中のものだけチェックされ、停止中・追加できるものは外れている
        $this->assertMatchesRegularExpression('/name="seats\['.$this->counter->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="seats\['.$this->table->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="seats\['.$addable->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
        // 追加できる座席の初期値は座席種別の既定値
        foreach ([[$this->counter->id, 'capacity', '10'], [$this->counter->id, 'price', '3000'], [$this->counter->id, 'max_people_per_reservation', '4'], [$addable->id, 'capacity', '6'], [$addable->id, 'price', '5000']] as [$id, $field, $value]) {
            $this->assertMatchesRegularExpression('/name="seats\['.$id.'\]\['.$field.'\]"[^>]*\bvalue="'.$value.'"/', $html);
        }
    }

    public function test_edit_shows_seat_of_inactive_seat_type_but_cannot_resume_it(): void
    {
        $stopped = BroadcastSeatType::factory()->forSeatType($this->table)->create(['broadcast_id' => $this->broadcast->id, 'is_active' => false]);
        $this->table->update(['is_active' => false]);

        $html = $this->actingAs($this->owner)->get($this->editUrl())->assertOk()
            ->assertSee('テーブル席')
            ->assertSee('座席種別が無効のため、販売を再開できません')
            ->getContent();

        $this->assertMatchesRegularExpression('/name="seats\['.$this->table->id.'\]\[selected\]"[^>]*\bdisabled\b/', $html);

        // 直接送られても、販売中に戻せない
        $this->update($this->validInput(['seats' => [
            $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
            $this->table->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
        ]]))->assertSessionHasErrors('seats');
        $this->assertSame(0, (int) $stopped->fresh()->is_active);
    }

    public function test_active_seat_of_inactive_seat_type_can_be_kept_or_stopped(): void
    {
        $this->counter->update(['is_active' => false]);

        // 販売中のまま保つ
        $this->update($this->validInput())->assertSessionHasNoErrors();
        $this->assertSame(1, (int) $this->counterSeat->fresh()->is_active);

        // 販売停止にする（ほかに販売中の座席を足す）
        $this->update($this->validInput(['seats' => [
            $this->table->id => ['selected' => '1', 'capacity' => '5', 'price' => '1000', 'max_people_per_reservation' => '2'],
        ]]))->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $this->counterSeat->fresh()->is_active);
    }

    public function test_index_shows_edit_link_only_for_unfinished_matches(): void
    {
        // キックオフを過ぎて3時間以内：一覧では「過去」だが、まだ終了とはみなさない
        $ongoing = $this->createFootballMatch(['kickoff_at' => '2026-10-31 10:00:00']);
        $ongoingBroadcast = $this->addBroadcast($this->shop, $ongoing, Broadcast::STATUS_PUBLISHED);
        $finished = $this->createFootballMatch(['kickoff_at' => '2026-10-31 08:59:59']);
        $finishedBroadcast = $this->addBroadcast($this->shop, $finished, Broadcast::STATUS_PUBLISHED);
        $finishedByStatus = $this->createFootballMatch(['kickoff_at' => '2026-10-31 11:00:00', 'status' => 'FINISHED']);
        $finishedByStatusBroadcast = $this->addBroadcast($this->shop, $finishedByStatus, Broadcast::STATUS_PUBLISHED);

        $upcoming = $this->actingAs($this->owner)->get($this->indexUrl())->assertOk();
        $upcoming->assertSee($this->editUrl(), false);

        $past = $this->actingAs($this->owner)->get(route('owner.broadcasts.index', ['shop' => $this->shop->id, 'period' => 'past']))->assertOk();
        $past->assertSee($this->editUrl($ongoingBroadcast), false)
            ->assertDontSee($this->editUrl($finishedBroadcast), false)
            ->assertDontSee($this->editUrl($finishedByStatusBroadcast), false);
    }

    // ---- 更新（正常系） ----

    public function test_update_changes_period_status_and_seat_and_redirects_with_message(): void
    {
        $response = $this->update($this->validInput([
            'reservation_start_at' => '2026-11-01T09:00',
            'reservation_end_at' => '2026-11-05T20:00',
            'status' => Broadcast::STATUS_DRAFT,
        ]));

        $response->assertRedirect($this->indexUrl())->assertSessionHas('success', '放映を更新しました。')->assertSessionHasNoErrors();
        $broadcast = $this->broadcast->fresh();
        // JST で入力した値が UTC で保存される
        $this->assertSame('2026-11-01 00:00:00', $broadcast->reservation_start_at);
        $this->assertSame('2026-11-05 11:00:00', $broadcast->reservation_end_at);
        $this->assertSame(Broadcast::STATUS_DRAFT, $broadcast->status);
        $seat = $this->counterSeat->fresh();
        $this->assertSame([8, 3500, 5], [$seat->capacity, $seat->price, $seat->max_people_per_reservation]);
        // 試合は変わらない
        $this->assertSame($this->match->id, $broadcast->football_match_id);

        $this->actingAs($this->owner)->get($this->indexUrl())->assertSee('放映を更新しました。')->assertSee('8席 / 3,500円');
    }

    public function test_draft_broadcast_can_be_published_and_reserved(): void
    {
        $this->broadcast->update(['status' => Broadcast::STATUS_DRAFT]);
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('matches.show', $this->match->id))->assertOk()->assertDontSee('バーA');
        $this->actingAs($customer)->get(route('reservations.create', $this->broadcast))->assertRedirect();

        $this->update($this->validInput(['status' => Broadcast::STATUS_PUBLISHED]))->assertSessionHasNoErrors();

        $this->actingAs($customer)->get(route('matches.show', $this->match->id))
            ->assertOk()
            ->assertSee('バーA')
            ->assertSee(route('reservations.create', $this->broadcast), false);
        $this->actingAs($customer)->post(route('reservations.confirm', $this->broadcast), [
            'broadcast_seat_type_id' => $this->counterSeat->id,
            'number_of_people' => 2,
        ])->assertOk();
        $this->actingAs($customer)->post(route('reservations.store', $this->broadcast))->assertRedirect();
        $reservation = Reservation::query()->sole();
        // 更新後の料金で予約される
        $this->assertSame(3500, $reservation->unit_price);
        $this->assertSame(7000, $reservation->total_price);
    }

    public function test_published_broadcast_can_be_unpublished_and_existing_reservation_remains(): void
    {
        $reservation = $this->reserve($this->counterSeat, 3);

        $this->update($this->validInput(['status' => Broadcast::STATUS_DRAFT]))->assertSessionHasNoErrors();

        $this->assertSame(Broadcast::STATUS_DRAFT, $this->broadcast->fresh()->status);
        // 一般ユーザーの試合詳細からは消える
        $this->actingAs(User::factory()->create())->get(route('matches.show', $this->match->id))->assertOk()->assertDontSee('バーA');
        // 既存の予約は、マイページ・店舗の予約一覧に残る
        $this->actingAs($reservation->user)->get(route('mypage'))->assertOk()->assertSee('バーA')->assertSee($reservation->reservation_code);
        $this->actingAs($this->owner)->get(route('owner.reservations.index'))->assertOk()->assertSee($reservation->reservation_code);
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
    }

    public function test_price_change_does_not_change_existing_reservation_amounts(): void
    {
        $reservation = $this->reserve($this->counterSeat, 3);

        $this->update($this->validInput(['seats' => [
            $this->counter->id => ['selected' => '1', 'capacity' => '10', 'price' => '9999', 'max_people_per_reservation' => '4'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame(9999, $this->counterSeat->fresh()->price);
        $reservation->refresh();
        $this->assertSame(3000, $reservation->unit_price);
        $this->assertSame(9000, $reservation->total_price);
    }

    public function test_unselected_available_seat_type_can_be_added(): void
    {
        $this->update($this->validInput(['seats' => [
            $this->counter->id => ['selected' => '1', 'capacity' => '10', 'price' => '3000', 'max_people_per_reservation' => '4'],
            $this->table->id => ['selected' => '1', 'capacity' => '12', 'price' => '2500', 'max_people_per_reservation' => '3'],
        ]]))->assertSessionHasNoErrors();

        $added = BroadcastSeatType::query()->where('broadcast_id', $this->broadcast->id)->where('seat_type_id', $this->table->id)->sole();
        $this->assertSame([12, 2500, 3, 1], [$added->capacity, $added->price, $added->max_people_per_reservation, (int) $added->is_active]);
        $this->assertSame(2, BroadcastSeatType::query()->count());
    }

    public function test_unchecked_seat_is_stopped_not_deleted_and_existing_reservation_stays_valid(): void
    {
        $tableSeat = BroadcastSeatType::factory()->forSeatType($this->table)->create(['broadcast_id' => $this->broadcast->id]);
        $reservation = $this->reserve($this->counterSeat, 3);

        // カウンター席のチェックを外す（実際の画面と同じく、席数などの欄は送る）
        $this->update($this->validInput(['seats' => [
            $this->counter->id => ['capacity' => '10', 'price' => '3000', 'max_people_per_reservation' => '4'],
            $this->table->id => ['selected' => '1', 'capacity' => '20', 'price' => '2000', 'max_people_per_reservation' => '4'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame(0, (int) $this->counterSeat->fresh()->is_active);
        $this->assertSame(1, (int) $tableSeat->fresh()->is_active);
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
        $this->assertSame($this->counterSeat->id, $reservation->fresh()->broadcast_seat_type_id);

        // 新しい予約だけが止まる
        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('選択された座席種別は予約できません。');
        app(ReservationService::class)->reserve(User::factory()->create(), $this->broadcast, $this->counterSeat->id, 1, 3000, null);
    }

    public function test_stopped_seat_can_be_resumed(): void
    {
        $this->counterSeat->update(['is_active' => false]);
        $this->assertSame(1, (int) $this->counter->fresh()->is_active);

        $this->update($this->validInput())->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $this->counterSeat->fresh()->is_active);
    }

    // ---- 予約との整合（席数の下限） ----

    public function test_capacity_exactly_equal_to_reserved_people_is_accepted_and_one_less_is_rejected(): void
    {
        $this->reserve($this->counterSeat, 3);
        $this->reserve($this->counterSeat, 2);
        // キャンセル済み・来店なしは席が空いているので数えない
        $this->reserve($this->counterSeat, 4, Reservation::STATUS_CANCELLED);
        $this->reserve($this->counterSeat, 4, Reservation::STATUS_NO_SHOW);

        $seats = fn (int $capacity) => [$this->counter->id => ['selected' => '1', 'capacity' => (string) $capacity, 'price' => '3000', 'max_people_per_reservation' => '4']];

        $editUrl = $this->editUrl();
        $this->actingAs($this->owner)->from($editUrl)->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput(['seats' => $seats(4)]))
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors(["seats.{$this->counter->id}.capacity" => '予約済みの人数（5人）より少ない席数にはできません。'])
            ->assertSessionHasInput('seats');
        $this->assertSame(10, $this->counterSeat->fresh()->capacity);

        $this->update($this->validInput(['seats' => $seats(5)]))->assertRedirect($this->indexUrl())->assertSessionHasNoErrors();
        $this->assertSame(5, $this->counterSeat->fresh()->capacity);
    }

    public function test_capacity_floor_includes_visited_people(): void
    {
        $this->reserve($this->counterSeat, 3);
        $this->reserve($this->counterSeat, 2, Reservation::STATUS_VISITED);

        $seats = fn (int $capacity) => [$this->counter->id => ['selected' => '1', 'capacity' => (string) $capacity, 'price' => '3000', 'max_people_per_reservation' => '4']];

        // 画面（FormRequest 経由）：confirmed 3 + visited 2 = 5 の1つ下は拒否、ちょうどは許可
        $editUrl = $this->editUrl();
        $this->actingAs($this->owner)->from($editUrl)->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput(['seats' => $seats(4)]))
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors(["seats.{$this->counter->id}.capacity" => '予約済みの人数（5人）より少ない席数にはできません。']);
        $this->assertSame(10, $this->counterSeat->fresh()->capacity);

        // サービス（ロックした後の確認）：1つ下は例外、ちょうどは通る
        $call = fn (int $capacity) => app(BroadcastService::class)->update($this->broadcast, Broadcast::STATUS_PUBLISHED, Carbon::parse('2026-11-01 00:00:00'), Carbon::parse('2026-11-05 12:00:00'), [
            ['seat_type_id' => $this->counter->id, 'capacity' => $capacity, 'price' => 3000, 'max_people_per_reservation' => 4],
        ]);
        try {
            $call(4);
            $this->fail('例外が投げられるはず');
        } catch (BroadcastUpdateException $e) {
            $this->assertSame(['seats.'.$this->counter->id.'.capacity'], array_keys($e->fieldErrors()));
        }
        $call(5);
        $this->assertSame(5, $this->counterSeat->fresh()->capacity);
    }

    public function test_capacity_error_is_shown_on_the_capacity_field_with_input_kept(): void
    {
        $this->reserve($this->counterSeat, 6);
        $editUrl = $this->editUrl();

        $this->actingAs($this->owner)->from($editUrl)->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput([
            'seats' => [$this->counter->id => ['selected' => '1', 'capacity' => '5', 'price' => '3000', 'max_people_per_reservation' => '4']],
        ]))->assertRedirect($editUrl);

        $this->get($editUrl)->assertOk()
            ->assertSee('予約済みの人数（6人）より少ない席数にはできません。')
            ->assertSee('value="5"', false);
    }

    public function test_capacity_below_reserved_people_of_a_stopped_seat_is_allowed_when_unchecked(): void
    {
        $this->reserve($this->counterSeat, 6);
        $this->actingAs($this->owner);

        // 販売停止にする座席は、席数を入力していても下限の対象にしない
        $this->update($this->validInput(['seats' => [
            $this->counter->id => ['capacity' => '1', 'price' => '3000', 'max_people_per_reservation' => '1'],
            $this->table->id => ['selected' => '1', 'capacity' => '5', 'price' => '1000', 'max_people_per_reservation' => '2'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame(10, $this->counterSeat->fresh()->capacity);
        $this->assertSame(0, (int) $this->counterSeat->fresh()->is_active);
    }

    public function test_service_judges_reserved_people_at_call_time_not_from_a_stale_model(): void
    {
        $staleBroadcast = Broadcast::query()->findOrFail($this->broadcast->id);
        // モデルを読んだ後に予約が増えた状況
        $this->reserve($this->counterSeat, 7);

        try {
            app(BroadcastService::class)->update($staleBroadcast, Broadcast::STATUS_PUBLISHED, Carbon::parse('2026-11-01 00:00:00'), Carbon::parse('2026-11-05 12:00:00'), [
                ['seat_type_id' => $this->counter->id, 'capacity' => 6, 'price' => 3000, 'max_people_per_reservation' => 4],
            ]);
            $this->fail('例外が投げられるはず');
        } catch (BroadcastUpdateException $e) {
            $this->assertSame(['seats.'.$this->counter->id.'.capacity'], array_keys($e->fieldErrors()));
        }
        $this->assertBroadcastUnchanged();
    }

    public function test_service_locks_seat_rows_before_any_other_select(): void
    {
        BroadcastSeatType::factory()->forSeatType($this->table)->create(['broadcast_id' => $this->broadcast->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(BroadcastService::class)->update($this->broadcast, Broadcast::STATUS_PUBLISHED, Carbon::parse('2026-11-01 00:00:00'), Carbon::parse('2026-11-05 12:00:00'), [
            ['seat_type_id' => $this->counter->id, 'capacity' => 6, 'price' => 3000, 'max_people_per_reservation' => 4],
        ]);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        // 最初の SELECT は、放映の座席種別の行を ID 順に取るもの（MySQL ではこれに FOR UPDATE が付く）。その後に放映・予約を読む
        $selects = array_values(array_filter($queries, fn (string $sql) => str_starts_with(strtolower($sql), 'select')));
        $this->assertStringContainsString('from `broadcast_seat_types`', strtolower(str_replace('"', '`', $selects[0])));
        $this->assertStringContainsString('order by `id` asc', strtolower(str_replace('"', '`', $selects[0])));
        $this->assertStringContainsString('from `broadcasts`', strtolower(str_replace('"', '`', $selects[1])));
        // MySQL では、最初の SELECT が行ロックであること（SQLite では FOR UPDATE が付かない）
        if (DB::getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', strtolower($selects[0]));
        }
    }

    public function test_service_rechecks_seat_type_availability_after_locking(): void
    {
        $staleBroadcast = Broadcast::query()->findOrFail($this->broadcast->id);
        // 検証の後に、座席種別が無効になった状況
        $this->table->update(['is_active' => false]);
        $this->counter->update(['is_active' => false]);
        $stopped = BroadcastSeatType::factory()->forSeatType($this->table)->create(['broadcast_id' => $this->broadcast->id, 'is_active' => false]);
        $extra = $this->addSeatType($this->shop, '個室', 6, 5000, false);

        foreach ([$this->table->id, $extra->id] as $seatTypeId) {
            try {
                app(BroadcastService::class)->update($staleBroadcast, Broadcast::STATUS_PUBLISHED, Carbon::parse('2026-11-01 00:00:00'), Carbon::parse('2026-11-05 12:00:00'), [
                    ['seat_type_id' => $this->counter->id, 'capacity' => 10, 'price' => 3000, 'max_people_per_reservation' => 4],
                    ['seat_type_id' => $seatTypeId, 'capacity' => 5, 'price' => 1000, 'max_people_per_reservation' => 2],
                ]);
                $this->fail('例外が投げられるはず');
            } catch (BroadcastUpdateException $e) {
                $this->assertSame(['seats'], array_keys($e->fieldErrors()));
            }
        }
        // 販売再開も新しい行の作成もされない
        $this->assertSame(0, (int) $stopped->fresh()->is_active);
        $this->assertSame(2, BroadcastSeatType::query()->count());
        $this->assertSame(Broadcast::STATUS_PUBLISHED, $this->broadcast->fresh()->status);
    }

    public function test_seat_type_unavailable_after_validation_returns_to_form_with_error(): void
    {
        // 検証は通るが、サービスのロック後の確認で使えないと判断される（検証を通した後に無効になった状況を再現）
        $this->app->bind(BroadcastService::class, fn () => new class extends BroadcastService
        {
            public function update(Broadcast $broadcast, string $status, Carbon $reservationStartAt, Carbon $reservationEndAt, array $seats): void
            {
                SeatType::query()->update(['is_active' => false]);
                BroadcastSeatType::query()->update(['is_active' => false]);
                parent::update($broadcast, $status, $reservationStartAt, $reservationEndAt, $seats);
            }
        });
        $editUrl = $this->editUrl();

        $this->actingAs($this->owner)->from($editUrl)->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput())
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors(['seats' => BroadcastService::SEAT_TYPE_UNAVAILABLE_MESSAGE])
            ->assertSessionHasInput('seats');
    }

    public function test_service_rejects_other_shops_seat_type_after_locking(): void
    {
        $other = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $otherSeat = $this->addSeatType($other, '他店席', 5, 1000);

        try {
            app(BroadcastService::class)->update($this->broadcast, Broadcast::STATUS_PUBLISHED, Carbon::parse('2026-11-01 00:00:00'), Carbon::parse('2026-11-05 12:00:00'), [
                ['seat_type_id' => $this->counter->id, 'capacity' => 10, 'price' => 3000, 'max_people_per_reservation' => 4],
                ['seat_type_id' => $otherSeat->id, 'capacity' => 5, 'price' => 1000, 'max_people_per_reservation' => 2],
            ]);
            $this->fail('例外が投げられるはず');
        } catch (BroadcastUpdateException $e) {
            $this->assertSame(['seats'], array_keys($e->fieldErrors()));
        }
        // 他店の座席種別の行は作られない
        $this->assertSame(1, BroadcastSeatType::query()->count());
    }

    // ---- 終了した試合 ----

    public function test_finished_match_cannot_be_edited_or_updated(): void
    {
        $finished = $this->createFootballMatch(['kickoff_at' => '2026-10-31 08:00:00']);
        $broadcast = $this->addBroadcast($this->shop, $finished, Broadcast::STATUS_DRAFT);
        $seat = $broadcast->broadcastSeatTypes()->sole();

        $this->actingAs($this->owner)->get($this->editUrl($broadcast))
            ->assertRedirect($this->indexUrl())
            ->assertSessionHas('error', BroadcastService::FINISHED_MESSAGE);
        $this->update($this->validInput(), $broadcast)
            ->assertRedirect($this->indexUrl())
            ->assertSessionHas('error', BroadcastService::FINISHED_MESSAGE);
        // 入力が不正でも、終了のメッセージを先に伝える（500 にならない）
        $this->update(['seats' => 'abc'], $broadcast)->assertRedirect($this->indexUrl());

        $this->assertSame(Broadcast::STATUS_DRAFT, $broadcast->fresh()->status);
        $this->assertSame(10, $seat->fresh()->capacity);
        $this->get($this->indexUrl())->assertSee(BroadcastService::FINISHED_MESSAGE);
    }

    public function test_match_is_editable_until_exactly_three_hours_after_kickoff(): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => '2026-10-31 09:00:00']);
        $broadcast = $this->addBroadcast($this->shop, $match, Broadcast::STATUS_DRAFT);

        // キックオフ＋3時間ちょうどは終了とみなす
        $this->travelTo('2026-10-31 11:59:59');
        $this->actingAs($this->owner)->get($this->editUrl($broadcast))->assertOk();
        $this->travelTo('2026-10-31 12:00:00');
        $this->actingAs($this->owner)->get($this->editUrl($broadcast))->assertRedirect($this->indexUrl());
    }

    #[DataProvider('finishedStatusProvider')]
    public function test_finished_status_match_cannot_be_edited(string $status): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => '2026-11-07 12:00:00', 'status' => $status]);
        $broadcast = $this->addBroadcast($this->shop, $match, Broadcast::STATUS_DRAFT);

        $this->actingAs($this->owner)->get($this->editUrl($broadcast))->assertRedirect($this->indexUrl());
        $this->update($this->validInput(['status' => Broadcast::STATUS_PUBLISHED]), $broadcast)->assertRedirect($this->indexUrl());
        $this->assertSame(Broadcast::STATUS_DRAFT, $broadcast->fresh()->status);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function finishedStatusProvider(): array
    {
        return [['FINISHED'], ['AWARDED'], ['CANCELLED']];
    }

    public function test_service_rechecks_finished_match_after_locking(): void
    {
        // リクエストの確認をすり抜けて終了した状況（モデルを読んだ後に終了した）
        $this->travelTo('2026-11-05 15:00:00');

        $this->expectException(BroadcastUpdateException::class);
        $this->expectExceptionMessage(BroadcastService::FINISHED_MESSAGE);
        try {
            app(BroadcastService::class)->update($this->broadcast, Broadcast::STATUS_DRAFT, Carbon::parse('2026-11-01 00:00:00'), Carbon::parse('2026-11-05 12:00:00'), [
                ['seat_type_id' => $this->counter->id, 'capacity' => 6, 'price' => 3000, 'max_people_per_reservation' => 4],
            ]);
        } finally {
            $this->assertSame(Broadcast::STATUS_PUBLISHED, $this->broadcast->fresh()->status);
        }
    }

    // ---- 入力チェック ----

    public function test_reservation_period_rules_are_the_same_as_store(): void
    {
        // キックオフ＋3時間ちょうどは可、1分後は不可
        $this->update($this->validInput(['reservation_end_at' => '2026-11-06T00:00']))->assertSessionHasNoErrors();
        $this->update($this->validInput(['reservation_end_at' => '2026-11-06T00:01']))->assertSessionHasErrors('reservation_end_at');
        // 開始 < 終了（同時刻は不可、1分後は可）
        $this->update($this->validInput(['reservation_start_at' => '2026-11-05T12:00', 'reservation_end_at' => '2026-11-05T12:00']))->assertSessionHasErrors('reservation_end_at');
        $this->update($this->validInput(['reservation_start_at' => '2026-11-05T12:00', 'reservation_end_at' => '2026-11-05T12:01']))->assertSessionHasNoErrors();
        // 必須
        $this->update($this->validInput(['reservation_start_at' => null]))->assertSessionHasErrors('reservation_start_at');
        $this->update($this->validInput(['reservation_end_at' => null]))->assertSessionHasErrors('reservation_end_at');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidInputProvider')]
    public function test_update_rejects_invalid_input(array $overrides, string $errorKey): void
    {
        if (isset($overrides['seat'])) {
            $overrides = ['seats' => [$this->counter->id => array_merge(
                ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
                $overrides['seat'],
            )]];
        }

        $this->update($this->validInput($overrides))->assertSessionHasErrors($errorKey);
        $this->assertBroadcastUnchanged();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'status missing' => [['status' => null], 'status'],
            'status unknown' => [['status' => 'archived'], 'status'],
            'no seats' => [['seats' => null], 'seats'],
            'capacity zero' => [['seat' => ['capacity' => '0']], 'seats.*.capacity'],
            'capacity over max' => [['seat' => ['capacity' => '65536']], 'seats.*.capacity'],
            'price negative' => [['seat' => ['price' => '-1']], 'seats.*.price'],
            'price over max' => [['seat' => ['price' => '1000001']], 'seats.*.price'],
            'max people zero' => [['seat' => ['max_people_per_reservation' => '0']], 'seats.*.max_people_per_reservation'],
            'max people over capacity' => [['seat' => ['capacity' => '8', 'max_people_per_reservation' => '9']], 'seats.*.max_people_per_reservation'],
        ];
    }

    public function test_max_people_equal_to_capacity_and_price_zero_are_accepted(): void
    {
        $this->update($this->validInput(['seats' => [
            $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '0', 'max_people_per_reservation' => '8'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame([8, 0, 8], [$this->counterSeat->fresh()->capacity, $this->counterSeat->fresh()->price, $this->counterSeat->fresh()->max_people_per_reservation]);
    }

    public function test_stopping_all_seats_is_rejected(): void
    {
        // 全部のチェックを外す（席数などの欄は送られる）
        $this->update($this->validInput(['seats' => [
            $this->counter->id => ['capacity' => '10', 'price' => '3000', 'max_people_per_reservation' => '4'],
        ]]))->assertSessionHasErrors(['seats' => '販売する座席を1つ以上選択してください。']);
        $this->update($this->validInput(['seats' => null]))->assertSessionHasErrors('seats');
        $this->assertBroadcastUnchanged();
    }

    public function test_other_shops_and_inactive_seat_types_cannot_be_added(): void
    {
        $other = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $otherSeat = $this->addSeatType($other, '他店席', 5, 1000);
        $inactive = $this->addSeatType($this->shop, '無効席', 5, 1000, false);

        foreach ([$otherSeat, $inactive] as $seatType) {
            $this->update($this->validInput(['seats' => [
                $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
                $seatType->id => ['selected' => '1', 'capacity' => '5', 'price' => '1000', 'max_people_per_reservation' => '2'],
            ]]))->assertSessionHasErrors('seats');
        }
        $this->assertBroadcastUnchanged();
    }

    public function test_extra_fields_do_not_override_server_side_values(): void
    {
        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $otherMatch = $this->createFootballMatch(['kickoff_at' => '2026-11-08 12:00:00']);

        $this->update($this->validInput([
            'shop_id' => $otherShop->id,
            'football_match_id' => $otherMatch->id,
            'id' => 99999,
            'title' => '乗っ取り',
            'notes' => '乗っ取り',
            'doors_open_at' => '2030-01-01 00:00:00',
            'seats' => [
                $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4', 'broadcast_id' => 99999, 'seat_type_id' => $this->table->id, 'is_active' => '0'],
            ],
        ]))->assertSessionHasNoErrors();

        $broadcast = $this->broadcast->fresh();
        $this->assertSame($this->shop->id, $broadcast->shop_id);
        $this->assertSame($this->match->id, $broadcast->football_match_id);
        $this->assertNull($broadcast->title);
        $this->assertNull($broadcast->notes);
        $this->assertNotSame('2030-01-01 00:00:00', $broadcast->doors_open_at);
        $seat = $this->counterSeat->fresh();
        $this->assertSame([$this->broadcast->id, $this->counter->id, 1], [$seat->broadcast_id, $seat->seat_type_id, (int) $seat->is_active]);
        $this->assertSame(1, BroadcastSeatType::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('malformedInputProvider')]
    public function test_update_with_malformed_input_does_not_error(array $overrides): void
    {
        $this->update($this->validInput($overrides))->assertSessionHasErrors();
        $this->assertBroadcastUnchanged();
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function malformedInputProvider(): array
    {
        return [
            'seats is a string' => [['seats' => 'abc']],
            'seat row is a string' => [['seats' => ['5' => 'abc']]],
            'seat fields are arrays' => [['seats' => ['5' => ['selected' => '1', 'capacity' => ['1'], 'price' => ['1'], 'max_people_per_reservation' => ['1']]]]],
            'seat fields are missing' => [['seats' => ['5' => ['selected' => '1']]]],
            'seat key is a string' => [['seats' => ['abc' => ['selected' => '1', 'capacity' => '5', 'price' => '100', 'max_people_per_reservation' => '4']]]],
            'seat key is negative' => [['seats' => ['-1' => ['selected' => '1', 'capacity' => '5', 'price' => '100', 'max_people_per_reservation' => '4']]]],
            'start is an array' => [['reservation_start_at' => ['x']]],
            'end is an array' => [['reservation_end_at' => ['x']]],
            'start has wrong format' => [['reservation_start_at' => '2026/11/01 09:00']],
            'status is an array' => [['status' => ['published']]],
        ];
    }

    // ---- エラーで戻ったときの表示 ----

    public function test_edit_after_error_checks_only_the_seats_that_were_selected(): void
    {
        $addable = $this->addSeatType($this->shop, '個室', 6, 5000);
        $editUrl = $this->editUrl();

        // 実際の画面と同じく、チェックを外した座席の席数なども送る。別の項目（受付終了）のエラーで戻す
        // 元はカウンター席が販売中。今回はカウンター席を外し、テーブル席（追加）だけをチェックして送る
        $this->actingAs($this->owner)->from($editUrl)->put(route('owner.broadcasts.update', $this->broadcast), $this->validInput([
            'reservation_end_at' => '2026-11-06T00:01',
            'seats' => [
                $this->counter->id => ['capacity' => '10', 'price' => '3000', 'max_people_per_reservation' => '4'],
                $this->table->id => ['selected' => '1', 'capacity' => '20', 'price' => '2000', 'max_people_per_reservation' => '4'],
                $addable->id => ['capacity' => '6', 'price' => '5000', 'max_people_per_reservation' => '4'],
            ],
        ]))->assertRedirect($editUrl)->assertSessionHasErrors('reservation_end_at');

        $html = $this->get($editUrl)->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/name="seats\['.$this->counter->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
        $this->assertMatchesRegularExpression('/name="seats\['.$this->table->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="seats\['.$addable->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
    }
}
