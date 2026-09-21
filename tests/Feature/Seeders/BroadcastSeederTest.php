<?php

namespace Tests\Feature\Seeders;

use App\Models\Broadcast;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\BroadcastSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class BroadcastSeederTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private function createShop(): Shop
    {
        return Shop::factory()->create([
            'user_id' => User::factory()->shopOwner()->create()->id,
        ]);
    }

    private function utc(mixed $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    // ---- 正常系（BroadcastFactory）----

    public function test_factory_for_match_builds_dates_from_kickoff_in_utc(): void
    {
        $kickoff = now()->addDays(30)->startOfMinute();
        $match = $this->createFootballMatch(['kickoff_at' => $kickoff]);

        $broadcast = Broadcast::factory()->forMatch($match)->create(['shop_id' => $this->createShop()->id]);

        $this->assertSame($match->id, $broadcast->football_match_id);
        $this->assertSame($this->utc($kickoff->copy()->subDays(14)), $this->utc($broadcast->reservation_start_at));
        $this->assertSame($this->utc($kickoff->copy()->subHours(3)), $this->utc($broadcast->reservation_end_at));
        $this->assertSame($this->utc($kickoff->copy()->subHour()), $this->utc($broadcast->doors_open_at));
        // 予約期間 → キックオフの前後関係が崩れていない
        $this->assertTrue(Carbon::parse($broadcast->reservation_start_at)->lt(Carbon::parse($broadcast->reservation_end_at)));
        $this->assertTrue(Carbon::parse($broadcast->reservation_end_at)->lt($kickoff));
    }

    public function test_factory_status_is_draft_or_published(): void
    {
        $shop = $this->createShop();

        foreach (range(1, 20) as $i) {
            $broadcast = Broadcast::factory()->forMatch($this->createFootballMatch())->create(['shop_id' => $shop->id]);
            $this->assertContains($broadcast->status, ['draft', 'published']);
        }
    }

    // ---- 正常系（BroadcastSeeder）----

    public function test_seeder_creates_three_to_five_unique_broadcasts_per_shop(): void
    {
        $shops = [$this->createShop(), $this->createShop()];
        foreach (range(1, 6) as $i) {
            $this->createFootballMatch();
        }

        (new BroadcastSeeder)->run();

        foreach ($shops as $shop) {
            $matchIds = Broadcast::where('shop_id', $shop->id)->pluck('football_match_id');

            $this->assertGreaterThanOrEqual(3, $matchIds->count());
            $this->assertLessThanOrEqual(5, $matchIds->count());
            // 同じ店舗で同じ試合が重複していない（UNIQUE(shop_id, football_match_id)）
            $this->assertSame($matchIds->count(), $matchIds->unique()->count());
        }
    }

    public function test_seeder_targets_only_matches_that_have_not_kicked_off(): void
    {
        $this->createShop();
        $past = $this->createFootballMatch(['kickoff_at' => now()->subDay(), 'status' => 'FINISHED']);
        foreach (range(1, 5) as $i) {
            $this->createFootballMatch();
        }

        (new BroadcastSeeder)->run();

        $this->assertSame(0, Broadcast::where('football_match_id', $past->id)->count());
        $this->assertGreaterThan(0, Broadcast::count());
    }

    public function test_seeder_limits_broadcasts_to_the_number_of_available_matches(): void
    {
        $shop = $this->createShop();
        $this->createFootballMatch();
        $this->createFootballMatch();

        (new BroadcastSeeder)->run();

        $this->assertSame(2, Broadcast::where('shop_id', $shop->id)->count());
    }

    // ---- 準異常系（前提データなし）----

    public function test_seeder_creates_nothing_when_there_are_no_matches(): void
    {
        $this->createShop();

        // Artisan 経由ではないため $this->command は null。それでも落ちない
        (new BroadcastSeeder)->run();

        $this->assertDatabaseCount('broadcasts', 0);
    }

    public function test_seeder_creates_nothing_when_all_matches_are_in_the_past(): void
    {
        $this->createShop();
        $this->createFootballMatch(['kickoff_at' => now()->subDays(3), 'status' => 'FINISHED']);

        (new BroadcastSeeder)->run();

        $this->assertDatabaseCount('broadcasts', 0);
    }

    // ---- 異常系（対象外の店舗）----

    public function test_seeder_skips_soft_deleted_shops(): void
    {
        $deleted = $this->createShop();
        $deleted->delete();
        $active = $this->createShop();
        foreach (range(1, 3) as $i) {
            $this->createFootballMatch();
        }

        (new BroadcastSeeder)->run();

        $this->assertSame(0, Broadcast::where('shop_id', $deleted->id)->count());
        $this->assertSame(3, Broadcast::where('shop_id', $active->id)->count());
    }
}
