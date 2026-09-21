<?php

namespace Database\Factories;

use App\Models\Broadcast;
use App\Models\FootballMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => null,
            'status' => random_int(1, 100) <= 80 ? 'published' : 'draft',
            'notes' => null,
        ];
    }

    /**
     * 試合を紐づけ、キックオフ時刻を基準に日時を組み立てる。
     * kickoff_at は UTC の Carbon なので、加減算した値もそのまま UTC で保存される。
     */
    public function forMatch(FootballMatch $match): static
    {
        return $this->state(fn (array $attributes) => [
            'football_match_id' => $match->id,
            'reservation_start_at' => $match->kickoff_at->copy()->subDays(14),
            'reservation_end_at' => $match->kickoff_at->copy()->subHours(3),
            'doors_open_at' => $match->kickoff_at->copy()->subHour(),
        ]);
    }
}
