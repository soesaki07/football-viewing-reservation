<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class MypageTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 今後・過去の境目を扱うので現在時刻を固定する
        $this->travelTo('2026-10-31 12:00:00');

        $this->user = User::factory()->create(['name' => '山田太郎', 'email' => 'taro@example.com']);
    }

    /**
     * 指定したキックオフ時刻の試合に対する予約を作る。
     *
     * @param  array<string, mixed>  $reservationState
     * @param  array<string, mixed>  $shopOverrides
     */
    private function addReservation(string $kickoffAt, ?User $user = null, array $reservationState = [], array $shopOverrides = []): Reservation
    {
        $match = $this->createFootballMatch(['kickoff_at' => $kickoffAt]);
        $shop = Shop::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'status' => Shop::STATUS_PUBLISHED,
        ], $shopOverrides));
        $broadcast = Broadcast::factory()->forMatch($match)->create([
            'shop_id' => $shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
        ]);
        $seatType = SeatType::factory()->create(['shop_id' => $shop->id, 'name' => 'カウンター席'.SeatType::count(), 'default_capacity' => 10, 'default_price' => 3000]);
        $broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);

        return Reservation::factory()->state(array_merge([
            'broadcast_seat_type_id' => $broadcastSeatType->id,
            'user_id' => ($user ?? $this->user)->id,
            'reservation_code' => strtoupper(substr(md5((string) microtime(true).$kickoffAt), 0, 8)),
        ], $reservationState))->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('mypage'))->assertRedirect(route('login'));
    }

    public function test_shows_user_name_and_email(): void
    {
        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('山田太郎')
            ->assertSee('taro@example.com');
    }

    public function test_shows_only_own_reservations(): void
    {
        $mine = $this->addReservation('2026-11-05 12:00:00', $this->user, ['reservation_code' => 'MINE2222']);
        $others = $this->addReservation('2026-11-06 12:00:00', User::factory()->create(), ['reservation_code' => 'OTHR3333']);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('MINE2222')
            ->assertSee(route('reservations.show', $mine), false)
            ->assertDontSee('OTHR3333')
            ->assertDontSee(route('reservations.show', $others), false);
    }

    public function test_shows_reservation_details(): void
    {
        $reservation = $this->addReservation('2026-11-05 12:00:00', null, ['total_price' => 12000, 'number_of_people' => 4], ['name' => 'テストバー渋谷']);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('Arsenal FC vs Chelsea FC')
            ->assertSee('2026/11/05 21:00') // JST 表示
            ->assertSee('テストバー渋谷')
            ->assertSee($reservation->broadcastSeatType->seatType->name)
            ->assertSee('4人')
            ->assertSee('12,000円')
            ->assertSee('予約確定')
            ->assertSee($reservation->reservation_code);
    }

    public function test_splits_upcoming_and_past_by_kickoff(): void
    {
        // キックオフちょうどは今後、1秒前は過去
        $this->addReservation('2026-10-31 12:00:00', null, ['reservation_code' => 'EXACT222']);
        $this->addReservation('2026-10-31 11:59:59', null, ['reservation_code' => 'BEFORE22']);
        $this->addReservation('2026-12-01 12:00:00', null, ['reservation_code' => 'FUTURE22']);

        $response = $this->actingAs($this->user)->get(route('mypage'))->assertOk();

        $response->assertSeeInOrder(['今後の予約', 'EXACT222', 'FUTURE22', '過去の予約', 'BEFORE22']);
    }

    public function test_orders_upcoming_ascending_and_past_descending(): void
    {
        $this->addReservation('2026-12-10 12:00:00', null, ['reservation_code' => 'UPLATE22']);
        $this->addReservation('2026-11-10 12:00:00', null, ['reservation_code' => 'UPEARLY2']);
        $this->addReservation('2026-09-10 12:00:00', null, ['reservation_code' => 'PASTOLD2']);
        $this->addReservation('2026-10-10 12:00:00', null, ['reservation_code' => 'PASTNEW2']);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertSeeInOrder(['UPEARLY2', 'UPLATE22', '過去の予約', 'PASTNEW2', 'PASTOLD2']);
    }

    public function test_orders_same_kickoff_by_id_descending_in_past(): void
    {
        $this->addReservation('2026-10-10 12:00:00', null, ['reservation_code' => 'SAMEFIRST']);
        $this->addReservation('2026-10-10 12:00:00', null, ['reservation_code' => 'SAMESECND']);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertSeeInOrder(['SAMESECND', 'SAMEFIRST']);
    }

    public function test_orders_same_kickoff_by_id_descending_in_upcoming(): void
    {
        $this->addReservation('2026-11-10 12:00:00', null, ['reservation_code' => 'UPFIRST2']);
        $this->addReservation('2026-11-10 12:00:00', null, ['reservation_code' => 'UPSECND2']);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertSeeInOrder(['今後の予約', 'UPSECND2', 'UPFIRST2', '過去の予約']);
    }

    public function test_shows_cancelled_reservation_with_label_in_both_sections(): void
    {
        $this->addReservation('2026-11-05 12:00:00', null, ['status' => Reservation::STATUS_CANCELLED, 'reservation_code' => 'CANCUP22']);
        $this->addReservation('2026-10-05 12:00:00', null, ['status' => Reservation::STATUS_CANCELLED, 'reservation_code' => 'CANCPAST']);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertSeeInOrder(['今後の予約', 'キャンセル済み', 'CANCUP22', '過去の予約', 'キャンセル済み', 'CANCPAST']);
    }

    public function test_shows_empty_messages(): void
    {
        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('今後の予約はありません。')
            ->assertSee('過去の予約はありません。');
    }

    public function test_shows_reservation_of_deleted_shop(): void
    {
        $this->addReservation('2026-11-05 12:00:00', null, ['reservation_code' => 'DELSHOP2'], ['name' => '閉店したバー', 'deleted_at' => now()]);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('DELSHOP2')
            ->assertSee('閉店したバー');
    }

    public function test_shows_favorite_teams_and_link(): void
    {
        $this->user->favoriteTeams()->attach([
            Team::create(['external_team_id' => 11, 'name' => 'Liverpool FC'])->id,
            Team::create(['external_team_id' => 12, 'name' => 'Everton FC'])->id,
        ]);

        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('Liverpool FC')
            ->assertSee('Everton FC')
            ->assertSee(route('select.teams'), false)
            ->assertDontSee('お気に入りチームはまだ登録されていません。');
    }

    public function test_shows_empty_favorite_teams_message(): void
    {
        $this->actingAs($this->user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('お気に入りチームはまだ登録されていません。')
            ->assertSee(route('select.teams'), false);
    }

    public function test_header_has_mypage_link_when_logged_in(): void
    {
        $this->actingAs($this->user)->get(route('mypage'))
            ->assertSee('マイページ')
            ->assertSee('href="'.route('mypage').'"', false);
    }

    public function test_does_not_cause_n_plus_one_queries(): void
    {
        foreach (range(1, 5) as $i) {
            $this->addReservation("2026-11-0{$i} 12:00:00");
        }
        $this->actingAs($this->user);

        DB::enableQueryLog();
        $this->get(route('mypage'))->assertOk();
        $queryCount = count(DB::getQueryLog());

        // 予約5件でも件数に比例して増えない（認証・予約2回・関連・お気に入り・セッション等）
        $this->assertLessThan(30, $queryCount);
    }
}
