<?php

namespace Tests\Feature\Seeders;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\BroadcastSeatTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class BroadcastSeatTypeSeederTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private function createShop(): Shop
    {
        return Shop::factory()->create([
            'user_id' => User::factory()->shopOwner()->create()->id,
        ]);
    }

    private function createSeatType(Shop $shop, string $name, array $overrides = []): SeatType
    {
        return SeatType::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'name' => $name,
            'default_capacity' => 8,
            'default_price' => 3000,
        ], $overrides));
    }

    private function createBroadcast(Shop $shop): Broadcast
    {
        return Broadcast::factory()->forMatch($this->createFootballMatch())->create(['shop_id' => $shop->id]);
    }

    // ---- 正常系（BroadcastSeatTypeFactory）----

    public function test_factory_for_seat_type_copies_capacity_and_price_from_seat_type(): void
    {
        $shop = $this->createShop();
        $seatType = $this->createSeatType($shop, 'テーブル席', ['default_capacity' => 12, 'default_price' => 3500]);
        $broadcast = $this->createBroadcast($shop);

        $broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);

        $this->assertSame($seatType->id, $broadcastSeatType->seat_type_id);
        $this->assertSame(12, $broadcastSeatType->capacity);
        $this->assertSame(3500, $broadcastSeatType->price);
        $this->assertSame(4, $broadcastSeatType->max_people_per_reservation);
        $this->assertTrue((bool) $broadcastSeatType->is_active);
    }

    // ---- 正常系（BroadcastSeatTypeSeeder）----

    public function test_seeder_links_all_active_seat_types_of_the_same_shop_only(): void
    {
        $shopA = $this->createShop();
        $shopB = $this->createShop();
        $this->createSeatType($shopA, 'カウンター席');
        $this->createSeatType($shopA, 'テーブル席');
        $this->createSeatType($shopB, 'ソファ席');
        $broadcastA = $this->createBroadcast($shopA);
        $broadcastB = $this->createBroadcast($shopB);

        (new BroadcastSeatTypeSeeder)->run();

        $this->assertSame(2, BroadcastSeatType::where('broadcast_id', $broadcastA->id)->count());
        $this->assertSame(1, BroadcastSeatType::where('broadcast_id', $broadcastB->id)->count());

        // 放映と同じ店舗の座席種別だけが紐づいている（他店舗の種別が混ざらない）
        foreach (BroadcastSeatType::with(['broadcast', 'seatType'])->get() as $broadcastSeatType) {
            $this->assertSame($broadcastSeatType->broadcast->shop_id, $broadcastSeatType->seatType->shop_id);
        }
    }

    public function test_seeder_inherits_capacity_and_price_from_seat_types(): void
    {
        $shop = $this->createShop();
        $this->createSeatType($shop, 'ソファ席', ['default_capacity' => 6, 'default_price' => 4500]);
        $this->createBroadcast($shop);

        (new BroadcastSeatTypeSeeder)->run();

        $broadcastSeatType = BroadcastSeatType::first();
        $this->assertSame(6, $broadcastSeatType->capacity);
        $this->assertSame(4500, $broadcastSeatType->price);
    }

    // ---- 準異常系（前提データなし）----

    public function test_seeder_creates_nothing_when_there_are_no_broadcasts(): void
    {
        $shop = $this->createShop();
        $this->createSeatType($shop, 'カウンター席');

        // Artisan 経由ではないため $this->command は null。それでも落ちない
        (new BroadcastSeatTypeSeeder)->run();

        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }

    public function test_seeder_creates_nothing_when_shop_has_no_seat_types(): void
    {
        $this->createBroadcast($this->createShop());

        (new BroadcastSeatTypeSeeder)->run();

        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }

    // ---- 異常系（対象外のデータ）----

    public function test_seeder_skips_inactive_seat_types(): void
    {
        $shop = $this->createShop();
        $this->createSeatType($shop, 'カウンター席');
        $inactive = $this->createSeatType($shop, 'テラス席', ['is_active' => false]);
        $broadcast = $this->createBroadcast($shop);

        (new BroadcastSeatTypeSeeder)->run();

        $this->assertSame(1, BroadcastSeatType::where('broadcast_id', $broadcast->id)->count());
        $this->assertSame(0, BroadcastSeatType::where('seat_type_id', $inactive->id)->count());
    }

    public function test_seeder_skips_broadcasts_of_soft_deleted_shops(): void
    {
        $shop = $this->createShop();
        $this->createSeatType($shop, 'カウンター席');
        $this->createBroadcast($shop);
        $shop->delete();

        (new BroadcastSeatTypeSeeder)->run();

        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }
}
