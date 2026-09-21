<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $businessHours = fake()->randomElement([
            ['15:00:00', '23:00:00'],
            ['16:00:00', '00:00:00'],
            ['17:00:00', '00:00:00'],
            ['17:00:00', '01:00:00'],
            ['18:00:00', '02:00:00'],
        ]);

        return [
            'name' => fake()->company(),
            'postal_code' => fake('ja_JP')->postcode(),
            'prefecture' => fake()->prefecture(),
            'city' => fake()->city(),
            'address_line' => fake()->streetAddress(),
            'latitude' => fake()->latitude(35.5, 35.9),
            'longitude' => fake()->longitude(139.5, 139.9),
            'google_place_id' => null,
            'phone_number' => fake()->phoneNumber(),
            'description' => fake()->randomElement([
                '大型スクリーンで欧州サッカーを観戦できるスポーツバーです。',
                '試合中継に合わせて特別メニューをご用意しています。',
                '仲間と盛り上がれる観戦席が充実したお店です。',
                'ヨーロッパのリーグ戦をライブで楽しめるバーです。',
                'お一人様でも気軽に観戦できる落ち着いた雰囲気のお店です。',
            ]),
            'opening_time' => $businessHours[0],
            'closing_time' => $businessHours[1],
            'website_url' => null,
            'status' => random_int(1, 100) <= 80 ? 'published' : 'draft',
            'deleted_at' => null,
        ];
    }
}
