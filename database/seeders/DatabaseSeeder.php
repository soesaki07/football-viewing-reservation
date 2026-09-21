<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 開発用のダミーデータ・管理者アカウントを作るため、本番では実行させない
        if (app()->isProduction()) {
            throw new \RuntimeException('開発用シーダーは本番環境では実行できません。');
        }

        $this->call([
            UserSeeder::class,
            ShopSeeder::class,
            SeatTypeSeeder::class,
            BroadcastSeeder::class,
            BroadcastSeatTypeSeeder::class,
        ]);
    }
}
