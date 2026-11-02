<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use App\Policies\ShopPolicy;
use App\Services\BroadcastService;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class OwnerBroadcastTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    private SeatType $counter;

    private SeatType $table;

    private FootballMatch $match;

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

    /**
     * 登録の入力。$overrides で上書きできる（キーに null を指定すると、その項目を送らない）。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        $input = array_merge([
            'shop_id' => $this->shop->id,
            'football_match_id' => $this->match->id,
            'reservation_start_at' => '2026-10-31T21:00',
            'reservation_end_at' => '2026-11-06T00:00',
            'status' => 'published',
            'seats' => [
                $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
            ],
        ], $overrides);

        return array_filter($input, fn ($value) => $value !== null);
    }

    private function addBroadcast(Shop $shop, FootballMatch $match, string $status = 'published', ?SeatType $seatType = null): Broadcast
    {
        $broadcast = Broadcast::factory()->forMatch($match)->create(['shop_id' => $shop->id, 'status' => $status]);
        BroadcastSeatType::factory()->forSeatType($seatType ?? $this->counter)->create(['broadcast_id' => $broadcast->id]);

        return $broadcast;
    }

    // ---- 認可 ----

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('owner.broadcasts.index'))->assertRedirect(route('login'));
        $this->get(route('owner.broadcasts.create'))->assertRedirect(route('login'));
        $this->post(route('owner.broadcasts.store'), $this->validInput())->assertRedirect(route('login'));
    }

    public function test_user_and_admin_get_403(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        foreach ([User::factory()->create(), $admin] as $user) {
            $this->actingAs($user)->get(route('owner.broadcasts.index'))->assertForbidden();
            $this->actingAs($user)->get(route('owner.broadcasts.create'))->assertForbidden();
            $this->actingAs($user)->post(route('owner.broadcasts.store'), $this->validInput())->assertForbidden();
        }
        $this->assertDatabaseCount('broadcasts', 0);
    }

    public function test_other_owners_shop_is_404_for_index_create_and_store(): void
    {
        $other = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $otherSeat = $this->addSeatType($other, '他店席', 5, 1000);

        $this->actingAs($this->owner);
        $this->get(route('owner.broadcasts.index', ['shop' => $other->id]))->assertNotFound();
        $this->get(route('owner.broadcasts.create', ['shop' => $other->id]))->assertNotFound();
        $this->post(route('owner.broadcasts.store'), $this->validInput([
            'shop_id' => $other->id,
            'seats' => [$otherSeat->id => ['selected' => '1', 'capacity' => '5', 'price' => '1000', 'max_people_per_reservation' => '4']],
        ]))->assertNotFound();
        $this->assertDatabaseCount('broadcasts', 0);
    }

    public function test_deleted_or_missing_shop_is_404(): void
    {
        $deleted = Shop::factory()->create(['user_id' => $this->owner->id]);
        $deleted->delete();

        $this->actingAs($this->owner);
        $this->get(route('owner.broadcasts.index', ['shop' => $deleted->id]))->assertNotFound();
        $this->get(route('owner.broadcasts.create', ['shop' => $deleted->id]))->assertNotFound();
        $this->get(route('owner.broadcasts.index', ['shop' => 999999]))->assertNotFound();
        $this->post(route('owner.broadcasts.store'), $this->validInput(['shop_id' => $deleted->id]))->assertNotFound();
        $this->post(route('owner.broadcasts.store'), $this->validInput(['shop_id' => 999999]))->assertNotFound();
        $this->post(route('owner.broadcasts.store'), $this->validInput(['shop_id' => null]))->assertNotFound();
    }

    public function test_shop_without_any_shops_shows_message(): void
    {
        $owner = User::factory()->shopOwner()->create();

        $this->actingAs($owner)->get(route('owner.broadcasts.index'))->assertOk()->assertSee('店舗が登録されていません。');
        $this->actingAs($owner)->get(route('owner.broadcasts.create'))->assertOk()->assertSee('店舗が登録されていません。');
    }

    public function test_policy_allows_only_owner_shop_owner(): void
    {
        $policy = new ShopPolicy;
        $other = User::factory()->shopOwner()->create();
        $user = User::factory()->create();

        $this->assertTrue($policy->manageBroadcasts($this->owner, $this->shop)->allowed());
        $this->assertSame(404, $policy->manageBroadcasts($other, $this->shop)->status());
        // shop_owner 以外は通常の拒否（Gate が 403 にする）
        $this->assertFalse($policy->manageBroadcasts($user, $this->shop)->allowed());
        $this->assertNull($policy->manageBroadcasts($user, $this->shop)->status());
    }

    // ---- 一覧 ----

    public function test_index_lists_upcoming_ascending_and_past_descending_with_reservation_counts(): void
    {
        $later = $this->createFootballMatch(['kickoff_at' => '2026-11-10 12:00:00']);
        $past1 = $this->createFootballMatch(['kickoff_at' => '2026-10-20 12:00:00']);
        $past2 = $this->createFootballMatch(['kickoff_at' => '2026-10-25 12:00:00']);

        $broadcastLater = $this->addBroadcast($this->shop, $later);
        $broadcastSoon = $this->addBroadcast($this->shop, $this->match, 'draft');
        $this->addBroadcast($this->shop, $past1);
        $this->addBroadcast($this->shop, $past2);

        // 予約数：confirmed の人数だけ数える
        $seat = $broadcastLater->broadcastSeatTypes()->first();
        Reservation::factory()->create(['broadcast_seat_type_id' => $seat->id, 'number_of_people' => 3, 'status' => 'confirmed']);
        Reservation::factory()->create(['broadcast_seat_type_id' => $seat->id, 'number_of_people' => 2, 'status' => 'cancelled']);

        $response = $this->actingAs($this->owner)->get(route('owner.broadcasts.index'));
        $response->assertOk();
        $response->assertSeeInOrder(['2026/11/05 21:00', '2026/11/10 21:00']);
        $response->assertDontSee('2026/10/20');
        $response->assertSee('3 / 10人');
        $response->assertSee('カウンター席：10席');
        $response->assertSee('非公開');
        $this->assertSame([$broadcastSoon->id, $broadcastLater->id], $response->viewData('broadcasts')->pluck('id')->all());

        $past = $this->actingAs($this->owner)->get(route('owner.broadcasts.index', ['period' => 'past']));
        $past->assertSeeInOrder(['2026/10/25 21:00', '2026/10/20 21:00']);
        $past->assertDontSee('2026/11/05');
    }

    public function test_index_pagination_is_japanese(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->addBroadcast($this->shop, $this->createFootballMatch(['kickoff_at' => now()->addDays($i)->toDateTimeString()]));
        }

        $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))
            ->assertOk()
            ->assertSee('件中', false)
            ->assertSee('件を表示', false)
            ->assertSee('次へ', false)
            ->assertDontSee('Showing')
            ->assertDontSee('results')
            ->assertDontSee('Next');
    }

    public function test_index_shows_reservation_period_in_jst(): void
    {
        $broadcast = $this->addBroadcast($this->shop, $this->match);
        $broadcast->update(['reservation_start_at' => '2026-11-01 00:00:00', 'reservation_end_at' => '2026-11-05 15:00:00']);

        $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))
            ->assertSee('2026/11/01 09:00')
            ->assertSee('2026/11/06 00:00');
    }

    public function test_index_does_not_show_other_shops_broadcasts(): void
    {
        $other = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id, 'name' => '他店バー']);
        $otherSeat = $this->addSeatType($other, '他店専用席', 5, 1000);
        $this->addBroadcast($other, $this->match, 'published', $otherSeat);

        $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))
            ->assertOk()
            ->assertDontSee('他店専用席')
            ->assertSee('条件に合う放映はありません。');
    }

    public function test_index_query_count_does_not_grow_with_broadcasts(): void
    {
        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->addBroadcast($this->shop, $this->match);
        $countQueries();
        $one = $countQueries();

        foreach (range(1, 4) as $day) {
            $match = $this->createFootballMatch(['kickoff_at' => now()->addDays($day + 10)]);
            $broadcast = $this->addBroadcast($this->shop, $match);
            BroadcastSeatType::factory()->forSeatType($this->table)->create(['broadcast_id' => $broadcast->id]);
        }
        $five = $countQueries();

        $this->assertSame($one, $five);
    }

    // ---- 入力画面 ----

    public function test_create_shows_competitions_and_defaults(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.broadcasts.create'));
        $response->assertOk()->assertSee('Premier League')->assertDontSee('name="reservation_start_at"', false);

        $competitionId = $this->match->competition_id;
        $step2 = $this->actingAs($this->owner)->get(route('owner.broadcasts.create', ['competition' => $competitionId, 'match' => $this->match->id]));
        $step2->assertOk()
            // 受付期間の初期値：開始は今（JST・分単位）、終了はキックオフ＋3時間（JST）
            ->assertSee('value="2026-10-31T21:00"', false)
            ->assertSee('value="2026-11-06T00:00"', false)
            // 座席の初期値
            ->assertSee('name="seats['.$this->counter->id.'][capacity]"', false)
            ->assertSee('value="10"', false)
            ->assertSee('value="3000"', false)
            ->assertSee('value="20"', false)
            ->assertSee('value="2000"', false)
            ->assertSee('value="4"', false);
    }

    public function test_create_lists_only_upcoming_unregistered_matches_of_the_competition(): void
    {
        $registered = $this->createFootballMatch(['kickoff_at' => '2026-11-07 12:00:00']);
        $this->addBroadcast($this->shop, $registered);
        $pastMatch = $this->createFootballMatch(['kickoff_at' => '2026-10-30 12:00:00']);
        $otherCompetition = Competition::create(['external_competition_id' => 2002, 'code' => 'BL1', 'name' => 'Bundesliga', 'area_name' => 'Germany', 'is_active' => true]);
        $otherCompetitionMatch = $this->createFootballMatch(['kickoff_at' => '2026-11-08 12:00:00', 'competition_id' => $otherCompetition->id]);
        // 他店が登録済みの試合は、この店舗では選べる
        $otherShop = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $this->addBroadcast($otherShop, $this->match, 'published', $this->addSeatType($otherShop, '他', 1, 1));

        $options = $this->actingAs($this->owner)
            ->get(route('owner.broadcasts.create', ['competition' => $this->match->competition_id]))
            ->assertOk()
            ->viewData('matchOptions');

        $this->assertSame([$this->match->id], $options->pluck('id')->all());
        $this->assertNotContains($registered->id, $options->pluck('id')->all());
        $this->assertNotContains($pastMatch->id, $options->pluck('id')->all());
        $this->assertNotContains($otherCompetitionMatch->id, $options->pluck('id')->all());
    }

    public function test_create_does_not_show_form_for_registered_or_past_match(): void
    {
        $this->addBroadcast($this->shop, $this->match);
        $past = $this->createFootballMatch(['kickoff_at' => '2026-10-30 12:00:00']);

        foreach ([$this->match, $past] as $match) {
            $this->actingAs($this->owner)
                ->get(route('owner.broadcasts.create', ['competition' => $match->competition_id, 'match' => $match->id]))
                ->assertOk()
                ->assertDontSee('name="reservation_start_at"', false);
        }
    }

    public function test_create_without_active_seat_types_shows_message(): void
    {
        $shop = Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーB']);
        $this->addSeatType($shop, '無効席', 5, 1000, false);

        $this->actingAs($this->owner)->get(route('owner.broadcasts.create', ['shop' => $shop->id]))
            ->assertOk()
            ->assertSee('有効な座席種別がないため、放映を登録できません。');
    }

    public function test_header_has_broadcast_link_for_shop_owner_only(): void
    {
        $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))->assertSee('放映管理');
        $this->actingAs(User::factory()->create())->get(route('matches.index'))->assertDontSee('放映管理');
    }

    // ---- 登録（正常系） ----

    public function test_store_creates_broadcast_and_seat_types_in_utc(): void
    {
        $response = $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'seats' => [
                $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
                $this->table->id => ['capacity' => '20', 'price' => '2000', 'max_people_per_reservation' => '4'],
            ],
        ]));

        $response->assertRedirect(route('owner.broadcasts.index', ['shop' => $this->shop->id]));
        $response->assertSessionHas('success', '放映を登録しました。');

        $broadcast = Broadcast::query()->sole();
        $this->assertSame($this->shop->id, $broadcast->shop_id);
        $this->assertSame($this->match->id, $broadcast->football_match_id);
        $this->assertSame('published', $broadcast->status);
        // JST 入力が UTC で保存される
        $this->assertSame('2026-10-31 12:00:00', $broadcast->reservation_start_at);
        $this->assertSame('2026-11-05 15:00:00', $broadcast->reservation_end_at);
        $this->assertNull($broadcast->title);
        $this->assertNull($broadcast->doors_open_at);
        $this->assertNull($broadcast->notes);

        // チェックを付けた座席だけ保存される
        $seat = BroadcastSeatType::query()->sole();
        $this->assertSame($broadcast->id, $seat->broadcast_id);
        $this->assertSame($this->counter->id, $seat->seat_type_id);
        $this->assertSame(8, $seat->capacity);
        $this->assertSame(3500, $seat->price);
        $this->assertSame(4, $seat->max_people_per_reservation);
        $this->assertEquals(1, $seat->is_active);
    }

    public function test_end_exactly_kickoff_plus_3_hours_is_accepted(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput(['reservation_end_at' => '2026-11-06T00:00']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('broadcasts', 1);
    }

    public function test_published_broadcast_appears_on_match_page_and_can_be_reserved(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'reservation_start_at' => '2026-10-31T21:00',
        ]))->assertSessionHasNoErrors();
        $broadcast = Broadcast::query()->sole();

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('matches.show', $this->match->id))
            ->assertOk()
            ->assertSee('バーA')
            ->assertSee(route('reservations.create', $broadcast), false);
        $this->actingAs($customer)->get(route('reservations.create', $broadcast))->assertOk();
    }

    public function test_draft_broadcast_does_not_appear_on_match_page(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput(['status' => 'draft']))
            ->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create())->get(route('matches.show', $this->match->id))
            ->assertOk()
            ->assertDontSee('バーA');
    }

    // ---- 入力チェック ----

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidInputProvider')]
    public function test_store_rejects_invalid_input(array $overrides, string $errorKey): void
    {
        // 座席の指定はテスト内で組み立てる（ID が実行時に決まるため）
        if (isset($overrides['seat'])) {
            $overrides = ['seats' => [$this->counter->id => array_merge(
                ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
                $overrides['seat'],
            )]];
            $errorKey = str_replace('{id}', (string) $this->counter->id, $errorKey);
        }

        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput($overrides))
            ->assertSessionHasErrors($errorKey);
        $this->assertDatabaseCount('broadcasts', 0);
        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'start is required' => [['reservation_start_at' => null], 'reservation_start_at'],
            'end is required' => [['reservation_end_at' => null], 'reservation_end_at'],
            'start format' => [['reservation_start_at' => '2026/10/31 21:00'], 'reservation_start_at'],
            'start equals end' => [['reservation_start_at' => '2026-11-01T10:00', 'reservation_end_at' => '2026-11-01T10:00'], 'reservation_end_at'],
            'start after end' => [['reservation_start_at' => '2026-11-02T10:00', 'reservation_end_at' => '2026-11-01T10:00'], 'reservation_end_at'],
            'end after kickoff plus 3 hours' => [['reservation_end_at' => '2026-11-06T00:01'], 'reservation_end_at'],
            'no seats' => [['seats' => null], 'seats'],
            'no seat selected' => [['seats' => [1 => ['capacity' => '8', 'price' => '100', 'max_people_per_reservation' => '4']]], 'seats'],
            'capacity zero' => [['seat' => ['capacity' => '0']], 'seats.{id}.capacity'],
            'capacity too large' => [['seat' => ['capacity' => '65536']], 'seats.{id}.capacity'],
            'capacity not integer' => [['seat' => ['capacity' => 'abc']], 'seats.{id}.capacity'],
            'price negative' => [['seat' => ['price' => '-1']], 'seats.{id}.price'],
            'price too large' => [['seat' => ['price' => '1000001']], 'seats.{id}.price'],
            'max people zero' => [['seat' => ['max_people_per_reservation' => '0']], 'seats.{id}.max_people_per_reservation'],
            'max people over capacity' => [['seat' => ['capacity' => '3', 'max_people_per_reservation' => '4']], 'seats.{id}.max_people_per_reservation'],
            'invalid status' => [['status' => 'archived'], 'status'],
            'status is required' => [['status' => null], 'status'],
            'match is required' => [['football_match_id' => null], 'football_match_id'],
            'match does not exist' => [['football_match_id' => 999999], 'football_match_id'],
        ];
    }

    public function test_price_zero_and_max_people_equal_to_capacity_are_accepted(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'seats' => [$this->counter->id => ['selected' => '1', 'capacity' => '4', 'price' => '0', 'max_people_per_reservation' => '4']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, BroadcastSeatType::query()->sole()->price);
    }

    public function test_store_rejects_started_match(): void
    {
        $started = $this->createFootballMatch(['kickoff_at' => '2026-10-31 11:00:00']);

        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput(['football_match_id' => $started->id, 'reservation_end_at' => '2026-10-31T21:00']))
            ->assertSessionHasErrors('football_match_id');
        $this->assertDatabaseCount('broadcasts', 0);
    }

    public function test_store_rejects_already_registered_match(): void
    {
        $this->addBroadcast($this->shop, $this->match);

        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput())
            ->assertSessionHasErrors(['football_match_id' => BroadcastService::ALREADY_REGISTERED_MESSAGE]);
        $this->assertDatabaseCount('broadcasts', 1);
    }

    public function test_same_match_can_be_registered_by_another_shop(): void
    {
        $otherShop = Shop::factory()->create(['user_id' => $this->owner->id]);
        $this->addBroadcast($otherShop, $this->match);

        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('broadcasts', 2);
    }

    // ---- IDOR・一括代入 ----

    public function test_store_rejects_other_shops_and_inactive_seat_types(): void
    {
        $other = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);
        $otherSeat = $this->addSeatType($other, '他店席', 5, 1000);
        $inactive = $this->addSeatType($this->shop, '無効席', 5, 1000, false);

        foreach ([$otherSeat->id, $inactive->id, 999999] as $seatTypeId) {
            $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
                'seats' => [
                    $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
                    $seatTypeId => ['selected' => '1', 'capacity' => '5', 'price' => '1000', 'max_people_per_reservation' => '4'],
                ],
            ]))->assertSessionHasErrors('seats');
        }
        $this->assertDatabaseCount('broadcasts', 0);
        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }

    public function test_store_rejects_non_numeric_seat_keys(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'seats' => ['abc' => ['selected' => '1', 'capacity' => '5', 'price' => '1000', 'max_people_per_reservation' => '4']],
        ]))->assertSessionHasErrors('seats');
        $this->assertDatabaseCount('broadcasts', 0);
    }

    public function test_extra_fields_do_not_override_server_side_values(): void
    {
        $other = Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id]);

        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'id' => 9999,
            'title' => '勝手なタイトル',
            'doors_open_at' => '2026-11-05 10:00:00',
            'notes' => 'メモ',
            'seats' => [$this->counter->id => [
                'selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4',
                'broadcast_id' => 9999, 'seat_type_id' => $this->table->id, 'is_active' => '0',
            ]],
        ]))->assertSessionHasNoErrors();

        $broadcast = Broadcast::query()->sole();
        $this->assertNotSame(9999, $broadcast->id);
        $this->assertSame($this->shop->id, $broadcast->shop_id);
        $this->assertNotSame($other->id, $broadcast->shop_id);
        $this->assertNull($broadcast->title);
        $this->assertNull($broadcast->doors_open_at);
        $this->assertNull($broadcast->notes);

        $seat = BroadcastSeatType::query()->sole();
        $this->assertSame($broadcast->id, $seat->broadcast_id);
        $this->assertSame($this->counter->id, $seat->seat_type_id);
        $this->assertEquals(1, $seat->is_active);
    }

    // ---- 同時登録 ----

    public function test_unique_violation_returns_to_form_instead_of_500(): void
    {
        $this->mock(BroadcastService::class)
            ->shouldReceive('create')
            ->once()
            ->andThrow(new UniqueConstraintViolationException('mysql', 'insert into broadcasts', [], new Exception('duplicate')));

        $response = $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput());

        $response->assertRedirect(route('owner.broadcasts.create', [
            'shop' => $this->shop->id,
            'competition' => $this->match->competition_id,
            'match' => $this->match->id,
        ]));
        $response->assertSessionHasErrors(['football_match_id' => BroadcastService::ALREADY_REGISTERED_MESSAGE]);
    }

    public function test_service_rolls_back_broadcast_when_seat_type_insert_fails(): void
    {
        try {
            app(BroadcastService::class)->create(
                $this->shop,
                $this->match->id,
                'published',
                now(),
                now()->addDay(),
                [
                    ['seat_type_id' => $this->counter->id, 'capacity' => 5, 'price' => 100, 'max_people_per_reservation' => 4],
                    // 同じ座席種別の2重指定で UNIQUE 違反になる
                    ['seat_type_id' => $this->counter->id, 'capacity' => 5, 'price' => 100, 'max_people_per_reservation' => 4],
                ],
            );
            $this->fail('例外が発生するはず');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('broadcasts', 0);
            $this->assertDatabaseCount('broadcast_seat_types', 0);
        }
    }

    // ---- 入力の型が不正なときも 500 にならない ----

    public function test_store_with_non_array_seats_returns_validation_error(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput(['seats' => 'abc']))
            ->assertSessionHasErrors('seats');
        $this->assertDatabaseCount('broadcasts', 0);
        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('malformedInputProvider')]
    public function test_store_with_malformed_input_does_not_error(array $overrides): void
    {
        $response = $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput($overrides));

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('broadcasts', 0);
        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function malformedInputProvider(): array
    {
        return [
            'seat row is a string' => [['seats' => ['5' => 'abc']]],
            'seat fields are arrays' => [['seats' => ['5' => ['selected' => '1', 'capacity' => ['1'], 'price' => ['1'], 'max_people_per_reservation' => ['1']]]]],
            'seat fields are missing' => [['seats' => ['5' => ['selected' => '1']]]],
            'seat key is a string' => [['seats' => ['abc' => ['selected' => '1', 'capacity' => '5', 'price' => '100', 'max_people_per_reservation' => '4']]]],
            'start is an array' => [['reservation_start_at' => ['x']]],
            'match id is an array' => [['football_match_id' => [1, 2]]],
            'status is an array' => [['status' => ['published']]],
        ];
    }

    public function test_store_reports_period_and_max_people_errors_at_once(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'reservation_start_at' => '2026-11-02T10:00',
            'reservation_end_at' => '2026-11-01T10:00',
            'seats' => [
                $this->counter->id => ['selected' => '1', 'capacity' => '3', 'price' => '100', 'max_people_per_reservation' => '4'],
                $this->table->id => ['selected' => '1', 'capacity' => '0', 'price' => '100', 'max_people_per_reservation' => '4'],
            ],
        ]))->assertSessionHasErrors([
            'reservation_end_at',
            "seats.{$this->counter->id}.max_people_per_reservation",
            "seats.{$this->table->id}.capacity",
        ]);
    }

    public function test_max_people_error_is_reported_even_when_period_is_invalid(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'reservation_end_at' => null,
            'seats' => [$this->counter->id => ['selected' => '1', 'capacity' => '3', 'price' => '100', 'max_people_per_reservation' => '4']],
        ]))->assertSessionHasErrors(['reservation_end_at', "seats.{$this->counter->id}.max_people_per_reservation"]);
    }

    public function test_period_error_is_reported_even_when_seat_is_invalid(): void
    {
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput([
            'reservation_end_at' => '2026-11-06T00:01',
            'seats' => [$this->counter->id => ['selected' => '1', 'capacity' => '0', 'price' => '100', 'max_people_per_reservation' => '4']],
        ]))->assertSessionHasErrors(['reservation_end_at', "seats.{$this->counter->id}.capacity"]);
    }

    public function test_non_scalar_shop_is_404(): void
    {
        // 配列が整数の 1 として解釈されても拾われないよう、ID 1 の自分の店舗を用意する
        if ($this->shop->id !== 1) {
            Shop::factory()->create(['id' => 1, 'user_id' => $this->owner->id]);
        }

        $this->actingAs($this->owner);
        $this->get(route('owner.broadcasts.index', ['shop' => [1]]))->assertNotFound();
        $this->get(route('owner.broadcasts.create', ['shop' => [1]]))->assertNotFound();
        $this->post(route('owner.broadcasts.store'), $this->validInput(['shop_id' => [1]]))->assertNotFound();
        $this->assertDatabaseCount('broadcasts', 0);
    }

    public function test_index_shows_unlimited_when_reservation_period_is_null(): void
    {
        $broadcast = $this->addBroadcast($this->shop, $this->match);
        $broadcast->update(['reservation_start_at' => null, 'reservation_end_at' => null]);

        $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))
            ->assertOk()
            ->assertSee('制限なし')
            ->assertDontSee('2026/10/31 21:00');
    }

    public function test_index_puts_kickoff_exactly_now_in_upcoming(): void
    {
        $exact = $this->createFootballMatch(['kickoff_at' => '2026-10-31 12:00:00']);
        $justBefore = $this->createFootballMatch(['kickoff_at' => '2026-10-31 11:59:59']);
        $exactBroadcast = $this->addBroadcast($this->shop, $exact);
        $beforeBroadcast = $this->addBroadcast($this->shop, $justBefore);

        $upcoming = $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))->viewData('broadcasts');
        $this->assertSame([$exactBroadcast->id], $upcoming->pluck('id')->all());

        $past = $this->actingAs($this->owner)->get(route('owner.broadcasts.index', ['period' => 'past']))->viewData('broadcasts');
        $this->assertSame([$beforeBroadcast->id], $past->pluck('id')->all());
    }

    public function test_total_price_limit_boundary_for_max_people(): void
    {
        $seat = fn (int $capacity, int $price, int $max) => [$this->counter->id => [
            'selected' => '1', 'capacity' => (string) $capacity, 'price' => (string) $price, 'max_people_per_reservation' => (string) $max,
        ]];

        // 524288 × 8192 = 4,294,967,296（上限より 1 大きい）
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput(['seats' => $seat(8192, 524288, 8192)]))
            ->assertSessionHasErrors("seats.{$this->counter->id}.max_people_per_reservation");
        $this->assertDatabaseCount('broadcasts', 0);

        // 65537 × 65535 = 4,294,967,295（ちょうど上限）
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput(['seats' => $seat(65535, 65537, 65535)]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('broadcasts', 1);
    }

    #[DataProvider('finishedStatusProvider')]
    public function test_finished_status_future_match_is_not_registrable_nor_listed(string $status): void
    {
        $finished = $this->createFootballMatch(['kickoff_at' => '2026-11-07 12:00:00', 'status' => $status]);

        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), $this->validInput(['football_match_id' => $finished->id]))
            ->assertSessionHasErrors('football_match_id');
        $this->assertDatabaseCount('broadcasts', 0);

        $options = $this->actingAs($this->owner)
            ->get(route('owner.broadcasts.create', ['competition' => $finished->competition_id]))
            ->viewData('matchOptions');
        $this->assertNotContains($finished->id, $options->pluck('id')->all());
        $this->assertContains($this->match->id, $options->pluck('id')->all());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function finishedStatusProvider(): array
    {
        return ['finished' => ['FINISHED'], 'awarded' => ['AWARDED'], 'cancelled' => ['CANCELLED']];
    }

    public function test_competition_with_only_finished_status_matches_is_not_listed(): void
    {
        $competition = Competition::create(['external_competition_id' => 2002, 'code' => 'BL1', 'name' => 'Bundesliga', 'area_name' => 'Germany', 'is_active' => true]);
        $this->createFootballMatch(['kickoff_at' => '2026-11-07 12:00:00', 'status' => 'CANCELLED', 'competition_id' => $competition->id]);

        $competitions = $this->actingAs($this->owner)->get(route('owner.broadcasts.create'))->viewData('competitions');

        $this->assertNotContains($competition->id, $competitions->pluck('id')->all());
    }

    public function test_create_after_error_checks_only_the_seats_that_were_selected(): void
    {
        $createUrl = route('owner.broadcasts.create', [
            'shop' => $this->shop->id,
            'competition' => $this->match->competition_id,
            'match' => $this->match->id,
        ]);

        // 実際の画面と同じく、チェックを外した座席の席数などの欄も送る。別の項目（受付終了）のエラーで戻す
        $this->actingAs($this->owner)->from($createUrl)->post(route('owner.broadcasts.store'), $this->validInput([
            'reservation_end_at' => '2026-11-06T00:01',
            'seats' => [
                $this->counter->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4'],
                $this->table->id => ['capacity' => '20', 'price' => '2000', 'max_people_per_reservation' => '4'],
            ],
        ]))->assertRedirect($createUrl)->assertSessionHasErrors('reservation_end_at');

        $html = $this->get($createUrl)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="seats\['.$this->counter->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="seats\['.$this->table->id.'\]\[selected\]"[^>]*\bchecked\b/', $html);
    }
}
