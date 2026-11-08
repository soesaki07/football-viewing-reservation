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
 * 予約の流れ・予約詳細の画面（3ステップの表示・予約コードの出し方）のテスト。
 */
class ReservationScreenTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $user;

    private Broadcast $broadcast;

    private BroadcastSeatType $broadcastSeatType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-31 12:00:00');

        $this->user = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => Shop::STATUS_PUBLISHED,
            'name' => 'テストバー新宿',
        ]);
        $this->broadcast = Broadcast::factory()->forMatch($this->createFootballMatch(['kickoff_at' => '2026-11-30 12:00:00']))->create([
            'shop_id' => $shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
            'reservation_start_at' => now()->subDay(),
            'reservation_end_at' => now()->addDay(),
        ]);
        $seatType = SeatType::factory()->create(['shop_id' => $shop->id, 'name' => 'ソファ席', 'default_capacity' => 10, 'default_price' => 3000]);
        $this->broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $this->broadcast->id]);
    }

    private function createReservation(array $state = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'broadcast_seat_type_id' => $this->broadcastSeatType->id,
            'user_id' => $this->user->id,
            'reservation_code' => 'ABCD2345',
            'number_of_people' => 2,
            'unit_price' => 3000,
            'total_price' => 6000,
        ], $state));
    }

    /**
     * 今のステップ（aria-current="step"）の項目に、そのステップの名前が入っていること。
     */
    private function assertCurrentStep(string $html, string $label): void
    {
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertMatchesRegularExpression('/aria-current="step"[^>]*>(?:(?!<\/li>).)*'.preg_quote($label, '/').'/s', $html);
    }

    public function test_create_screen_marks_step_1_as_current(): void
    {
        $html = $this->actingAs($this->user)->get(route('reservations.create', $this->broadcast))
            ->assertOk()
            ->assertSee('data-testid="reservation-steps"', false)
            ->getContent();

        $this->assertCurrentStep($html, '予約情報入力');
    }

    public function test_confirm_screen_marks_step_2_as_current_and_hides_reservation_code(): void
    {
        $response = $this->actingAs($this->user)->post(route('reservations.confirm', $this->broadcast), [
            'broadcast_seat_type_id' => $this->broadcastSeatType->id,
            'number_of_people' => 2,
        ])->assertOk();

        $this->assertCurrentStep($response->getContent(), '内容確認');
        // 予約コードは確定したときに発行されるので、確認画面には出さない
        $response->assertDontSee('予約コード')
            ->assertDontSee('data-testid="reservation-code"', false)
            ->assertSee('6,000円');
    }

    public function test_complete_screen_marks_step_3_as_current_and_shows_reservation_code(): void
    {
        $reservation = $this->createReservation();

        $response = $this->actingAs($this->user)->get(route('reservations.complete', $reservation))
            ->assertOk()
            ->assertSee('data-testid="reservation-code"', false)
            ->assertSee('ABCD2345');

        $this->assertCurrentStep($response->getContent(), '予約完了');
    }

    public function test_show_screen_shows_code_unit_price_address_and_reserved_at(): void
    {
        $reservation = $this->createReservation(['reserved_at' => '2026-10-31 12:30:00']);

        $this->actingAs($this->user)->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('data-testid="reservation-code"', false)
            ->assertSee('ABCD2345')
            ->assertSee('3,000円')
            ->assertSee('2026/10/31 21:30') // 予約日時（JST 表示）
            ->assertSee($this->broadcast->shop->fullAddress())
            ->assertDontSee('キャンセル日時');
    }

    public function test_cancelled_reservation_shows_cancelled_at_and_no_cancel_button(): void
    {
        $reservation = $this->createReservation([
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => '2026-10-31 11:00:00',
        ]);

        $this->actingAs($this->user)->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('キャンセル済み')
            ->assertSee('キャンセル日時')
            ->assertSee('2026/10/31 20:00') // キャンセル日時（JST 表示）
            ->assertDontSee('この予約をキャンセルする')
            ->assertDontSee('data-confirm', false);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function statusBadgeProvider(): array
    {
        return [
            '予約確定は success' => [Reservation::STATUS_CONFIRMED, '予約確定', 'bg-tertiary-fixed '],
            '来店済みは success' => [Reservation::STATUS_VISITED, '来店済み', 'bg-tertiary-fixed '],
            '来店なしは danger' => [Reservation::STATUS_NO_SHOW, '来店なし', 'bg-error-container '],
            'キャンセル済みは neutral' => [Reservation::STATUS_CANCELLED, 'キャンセル済み', 'bg-surface-container-high '],
        ];
    }

    #[DataProvider('statusBadgeProvider')]
    public function test_status_badge_color_follows_status(string $status, string $label, string $colorClass): void
    {
        $reservation = $this->createReservation(['status' => $status]);

        $html = $this->actingAs($this->user)->get(route('reservations.show', $reservation))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<span[^>]*'.preg_quote($colorClass, '/').'[^>]*>\s*'.preg_quote($label, '/').'\s*<\/span>/u', $html);
    }

    public function test_create_screen_keeps_input_after_validation_error(): void
    {
        $this->actingAs($this->user)
            ->from(route('reservations.create', $this->broadcast))
            ->followingRedirects()
            ->post(route('reservations.confirm', $this->broadcast), [
                'broadcast_seat_type_id' => $this->broadcastSeatType->id,
                'number_of_people' => 0,
                'customer_note' => '窓際希望です',
            ])
            ->assertOk()
            ->assertSee('data-testid="reservation-steps"', false)
            ->assertSee('value="0"', false)
            ->assertSee('窓際希望です')
            ->assertSee('aria-invalid="true"', false);
    }
}
