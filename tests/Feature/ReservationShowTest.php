<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class ReservationShowTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $user;

    private Shop $shop;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-31 12:00:00');

        $this->user = User::factory()->create();
        $this->shop = Shop::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => Shop::STATUS_PUBLISHED,
            'name' => 'テストバー新宿',
        ]);
        $broadcast = Broadcast::factory()->forMatch($this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']))->create([
            'shop_id' => $this->shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
        ]);
        $seatType = SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => 'ソファ席', 'default_capacity' => 10, 'default_price' => 3000]);
        $broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);

        $this->reservation = Reservation::factory()->create([
            'broadcast_seat_type_id' => $broadcastSeatType->id,
            'user_id' => $this->user->id,
            'reservation_code' => 'ABCD2345',
            'number_of_people' => 3,
            'total_price' => 9000,
            'customer_note' => '<b>窓際</b>希望',
        ]);
    }

    public function test_owner_can_see_reservation_details(): void
    {
        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertSee('ABCD2345')
            ->assertSee('予約確定')
            ->assertSee('Arsenal FC vs Chelsea FC')
            ->assertSee('2026/11/05 21:00')
            ->assertSee('テストバー新宿')
            ->assertSee('ソファ席')
            ->assertSee('3人')
            ->assertSee('9,000円')
            ->assertSee(route('mypage'), false)
            ->assertSee(route('matches.show', $this->reservation->broadcastSeatType->broadcast->football_match_id), false);
    }

    public function test_customer_note_is_escaped(): void
    {
        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertSee('&lt;b&gt;窓際&lt;/b&gt;希望', false)
            ->assertDontSee('<b>窓際</b>', false);
    }

    public function test_does_not_show_cancel_button(): void
    {
        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertDontSee('キャンセルする');
    }

    public function test_other_users_reservation_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reservations.show', $this->reservation))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reservations.show', $this->reservation))->assertRedirect(route('login'));
    }

    public function test_missing_reservation_is_not_found(): void
    {
        $this->actingAs($this->user)->get(route('reservations.show', 999999))->assertNotFound();
    }

    public function test_deleted_shop_reservation_is_still_visible(): void
    {
        $this->shop->delete();

        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertSee('テストバー新宿');
    }

    public function test_cancelled_reservation_shows_cancelled_label(): void
    {
        $this->reservation->update(['status' => Reservation::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $this->actingAs($this->user)->get(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertSee('キャンセル済み');
    }

    public function test_status_label_for_each_status(): void
    {
        $this->assertSame('予約確定', (new Reservation(['status' => Reservation::STATUS_CONFIRMED]))->statusLabel());
        $this->assertSame('キャンセル済み', (new Reservation(['status' => Reservation::STATUS_CANCELLED]))->statusLabel());
        $this->assertSame('来店済み', (new Reservation(['status' => Reservation::STATUS_VISITED]))->statusLabel());
        $this->assertSame('来店なし', (new Reservation(['status' => Reservation::STATUS_NO_SHOW]))->statusLabel());
        $this->assertSame('unknown', (new Reservation(['status' => 'unknown']))->statusLabel());
    }
}
