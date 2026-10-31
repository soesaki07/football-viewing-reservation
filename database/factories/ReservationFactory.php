<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_code' => strtoupper(Str::random(8)),
            'user_id' => User::factory(),
            'number_of_people' => 2,
            'unit_price' => 3000,
            'total_price' => 6000,
            'status' => Reservation::STATUS_CONFIRMED,
            'reserved_at' => now()->utc(),
            'customer_note' => null,
        ];
    }

    /**
     * キャンセル済みの予約にする。
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => now()->utc(),
        ]);
    }
}
