<?php

namespace Database\Seeders;

use App\Models\SeatType;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class SeatTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 種別ごとの固定値（default_price は1人あたり・円）
        $seatTypes = [
            [
                'name' => 'カウンター席',
                'description' => 'スクリーンが見やすいカウンター席です。お一人様でも気軽にご利用いただけます。',
                'default_capacity' => 8,
                'default_price' => 3000,
            ],
            [
                'name' => 'テーブル席',
                'description' => '仲間と盛り上がれるテーブル席です。飲食を楽しみながら観戦できます。',
                'default_capacity' => 12,
                'default_price' => 3500,
            ],
            [
                'name' => 'ソファ席',
                'description' => 'ゆったり座れるソファ席です。腰を据えてじっくり観戦したい方におすすめです。',
                'default_capacity' => 6,
                'default_price' => 4500,
            ],
            [
                'name' => 'テラス席',
                'description' => '屋外のテラス席です。開放的な雰囲気で観戦をお楽しみいただけます。',
                'default_capacity' => 10,
                'default_price' => 3000,
            ],
        ];

        foreach (Shop::all() as $shop) {
            // 店ごとに2〜3種類を重複なしで選ぶ（UNIQUE(shop_id, name) 対策）
            $selected = fake()->randomElements($seatTypes, fake()->numberBetween(2, 3));

            foreach ($selected as $seatType) {
                SeatType::factory()->create(
                    array_merge(['shop_id' => $shop->id], $seatType)
                );
            }
        }
    }
}
