<?php

namespace App\Services;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Shop;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BroadcastService
{
    public const ALREADY_REGISTERED_MESSAGE = 'この試合はすでに登録されています。';

    /**
     * 放映と、その座席種別を1つのトランザクションで登録する。
     * 店舗・試合・座席種別の ID は、呼び出し側が認可・検証した値を渡すこと（リクエストの値をそのまま渡さない）。
     * 日時は UTC の Carbon で受け取り、そのまま保存する。
     *
     * @param  list<array{seat_type_id: int, capacity: int, price: int, max_people_per_reservation: int}>  $seats
     *
     * @throws UniqueConstraintViolationException 同じ店舗・同じ試合の放映がすでにあるとき（同時登録）
     */
    public function create(Shop $shop, int $footballMatchId, string $status, Carbon $reservationStartAt, Carbon $reservationEndAt, array $seats): Broadcast
    {
        return DB::transaction(function () use ($shop, $footballMatchId, $status, $reservationStartAt, $reservationEndAt, $seats) {
            $broadcast = Broadcast::create([
                'shop_id' => $shop->id,
                'football_match_id' => $footballMatchId,
                'reservation_start_at' => $reservationStartAt,
                'reservation_end_at' => $reservationEndAt,
                'status' => $status,
            ]);

            foreach ($seats as $seat) {
                BroadcastSeatType::create([
                    'broadcast_id' => $broadcast->id,
                    'seat_type_id' => $seat['seat_type_id'],
                    'capacity' => $seat['capacity'],
                    'price' => $seat['price'],
                    'max_people_per_reservation' => $seat['max_people_per_reservation'],
                    'is_active' => true,
                ]);
            }

            return $broadcast;
            // 同時登録でデッドロックになったときは、Laravel が自動で再試行する（最大3回）
        }, attempts: 3);
    }
}
