<?php

namespace Database\Seeders;

use App\Models\Broadcast;
use App\Models\FootballMatch;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class BroadcastSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 放映の対象は、まだキックオフしていない試合のみ
        $matches = FootballMatch::where('kickoff_at', '>', now())->get();

        if ($matches->isEmpty()) {
            $this->command?->warn('放映対象の試合がありません。先に試合の同期を実行してください。');

            return;
        }

        foreach (Shop::all() as $shop) {
            // 店ごとに3〜5試合を重複なしで選ぶ（UNIQUE(shop_id, football_match_id) 対策）
            $count = min(fake()->numberBetween(3, 5), $matches->count());

            foreach ($matches->random($count) as $match) {
                Broadcast::factory()->forMatch($match)->create([
                    'shop_id' => $shop->id,
                ]);
            }
        }
    }
}
