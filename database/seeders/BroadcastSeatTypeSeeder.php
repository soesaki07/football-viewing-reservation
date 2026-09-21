<?php

namespace Database\Seeders;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use Illuminate\Database\Seeder;

class BroadcastSeatTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $broadcasts = Broadcast::with('shop.seatTypes')->get();

        if ($broadcasts->isEmpty()) {
            $this->command?->warn('放映がありません。先に BroadcastSeeder を実行してください。');

            return;
        }

        foreach ($broadcasts as $broadcast) {
            // 論理削除された店舗の放映は対象外
            if ($broadcast->shop === null) {
                continue;
            }

            // 放映と同じ店舗の、有効な座席種別だけを紐づける
            foreach ($broadcast->shop->seatTypes->where('is_active', true) as $seatType) {
                BroadcastSeatType::factory()->forSeatType($seatType)->create([
                    'broadcast_id' => $broadcast->id,
                ]);
            }
        }
    }
}
