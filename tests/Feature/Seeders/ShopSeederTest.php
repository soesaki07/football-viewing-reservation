<?php

namespace Tests\Feature\Seeders;

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\ShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSeederTest extends TestCase
{
    use RefreshDatabase;

    // ---- 正常系 ----

    public function test_seeder_creates_one_shop_per_shop_owner(): void
    {
        $owners = User::factory()->shopOwner()->count(3)->create();

        (new ShopSeeder)->run();

        $this->assertSame(3, Shop::count());
        foreach ($owners as $owner) {
            $this->assertSame(1, Shop::where('user_id', $owner->id)->count());
        }
        foreach (Shop::all() as $shop) {
            $this->assertContains($shop->status, ['draft', 'published']);
            $this->assertNotEmpty($shop->description);
        }
    }

    // ---- 準異常系（前提データなし）----

    public function test_seeder_creates_no_shops_when_there_are_no_shop_owners(): void
    {
        (new ShopSeeder)->run();

        $this->assertDatabaseCount('shops', 0);
    }

    // ---- 異常系（対象外のユーザー）----

    public function test_seeder_does_not_create_shops_for_non_owner_users(): void
    {
        User::factory()->count(2)->create();
        User::factory()->create(['role' => 'admin']);

        (new ShopSeeder)->run();

        $this->assertDatabaseCount('shops', 0);
    }
}
