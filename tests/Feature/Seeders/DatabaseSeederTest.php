<?php

namespace Tests\Feature\Seeders;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['seeder.admin_password' => 'test-admin-password']);
    }

    // ---- 正常系 ----

    public function test_seeds_users_shops_seat_types_broadcasts_and_broadcast_seat_types(): void
    {
        foreach (range(1, 6) as $i) {
            $this->createFootballMatch();
        }

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(51, User::count());
        $this->assertSame(10, Shop::count());
        $this->assertGreaterThan(0, Broadcast::count());
        $this->assertGreaterThan(0, BroadcastSeatType::count());

        // 放映の座席種別は、放映と同じ店舗のものだけ
        foreach (BroadcastSeatType::with(['broadcast', 'seatType'])->get() as $broadcastSeatType) {
            $this->assertSame($broadcastSeatType->broadcast->shop_id, $broadcastSeatType->seatType->shop_id);
        }
    }

    // ---- 準異常系（試合データなし）----

    public function test_seeds_without_broadcasts_when_no_matches_exist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(51, User::count());
        $this->assertSame(10, Shop::count());
        $this->assertDatabaseCount('broadcasts', 0);
        $this->assertDatabaseCount('broadcast_seat_types', 0);
    }

    // ---- 異常系（本番環境）----

    public function test_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            (new DatabaseSeeder)->run();
            $this->fail('本番環境では例外が投げられるはずです。');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('本番環境', $e->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
