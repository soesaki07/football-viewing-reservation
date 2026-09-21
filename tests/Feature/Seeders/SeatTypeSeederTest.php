<?php

namespace Tests\Feature\Seeders;

use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\SeatTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    private function createShop(): Shop
    {
        return Shop::factory()->create([
            'user_id' => User::factory()->shopOwner()->create()->id,
        ]);
    }

    // ---- 正常系 ----

    public function test_seeder_creates_two_or_three_unique_seat_types_per_shop(): void
    {
        $shops = collect(range(1, 5))->map(fn () => $this->createShop());

        (new SeatTypeSeeder)->run();

        foreach ($shops as $shop) {
            $names = SeatType::where('shop_id', $shop->id)->pluck('name');

            $this->assertGreaterThanOrEqual(2, $names->count());
            $this->assertLessThanOrEqual(3, $names->count());
            // 同じ店舗内で座席名が重複していない（UNIQUE(shop_id, name)）
            $this->assertSame($names->count(), $names->unique()->count());
        }
    }

    public function test_seeder_sets_capacity_price_and_description_per_seat_type(): void
    {
        $this->createShop();
        $this->createShop();
        $this->createShop();

        (new SeatTypeSeeder)->run();

        $expected = [
            'カウンター席' => ['capacity' => 8, 'price' => 3000],
            'テーブル席' => ['capacity' => 12, 'price' => 3500],
            'ソファ席' => ['capacity' => 6, 'price' => 4500],
            'テラス席' => ['capacity' => 10, 'price' => 3000],
        ];

        foreach (SeatType::all() as $seatType) {
            $this->assertArrayHasKey($seatType->name, $expected);
            $this->assertSame($expected[$seatType->name]['capacity'], $seatType->default_capacity);
            $this->assertSame($expected[$seatType->name]['price'], $seatType->default_price);
            $this->assertNotEmpty($seatType->description);
            $this->assertTrue((bool) $seatType->is_active);
        }
    }

    // ---- 準異常系（前提データなし）----

    public function test_seeder_creates_no_seat_types_when_there_are_no_shops(): void
    {
        (new SeatTypeSeeder)->run();

        $this->assertDatabaseCount('seat_types', 0);
    }

    // ---- 異常系（対象外の店舗）----

    public function test_seeder_skips_soft_deleted_shops(): void
    {
        $deleted = $this->createShop();
        $deleted->delete();
        $active = $this->createShop();

        (new SeatTypeSeeder)->run();

        $this->assertSame(0, SeatType::where('shop_id', $deleted->id)->count());
        $this->assertGreaterThan(0, SeatType::where('shop_id', $active->id)->count());
    }
}
