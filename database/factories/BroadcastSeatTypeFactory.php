<?php

namespace Database\Factories;

use App\Models\BroadcastSeatType;
use App\Models\SeatType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastSeatType>
 */
class BroadcastSeatTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'max_people_per_reservation' => 4,
            'is_active' => true,
        ];
    }

    /**
     * 座席種別を紐づけ、販売席数と料金を座席種別のデフォルト値から引き継ぐ。
     */
    public function forSeatType(SeatType $seatType): static
    {
        return $this->state(fn (array $attributes) => [
            'seat_type_id' => $seatType->id,
            'capacity' => $seatType->default_capacity,
            'price' => $seatType->default_price,
        ]);
    }
}
