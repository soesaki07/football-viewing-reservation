<?php

namespace App\Services;

use App\Exceptions\BroadcastUpdateException;
use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BroadcastService
{
    public const ALREADY_REGISTERED_MESSAGE = 'この試合はすでに登録されています。';

    public const FINISHED_MESSAGE = 'この試合は終了しているため、放映を編集できません。';

    public const SEAT_TYPE_UNAVAILABLE_MESSAGE = '選択された座席種別は登録できません。';

    public const NO_RESERVABLE_SEAT_MESSAGE = '予約できる座席（座席種別が有効なもの）を1つ以上販売してください。';

    public const SEAT_BELOW_RESERVED_MESSAGE = '予約済みの人数（%d人）より少ない席数にはできません。';

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

    /**
     * 放映の受付期間・ステータス・座席を、1つのトランザクションで更新する。
     * 放映の座席種別の行を ID 順にロックし（予約の確定と同じ行・同じ順番）、ロックした後の予約数で席数の下限を確かめ直す。
     * $seats に含まれない既存の座席は販売停止（is_active = false）にする。行は消さない。
     * 日時は UTC の Carbon で受け取り、そのまま保存する。
     *
     * 注意：
     * - 同じトランザクションの中で、ロックより前に普通の SELECT をしないこと（ReservationService::reserve と同じ理由）。
     * - 外側のトランザクションの中から呼ばないこと。
     *
     * @param  list<array{seat_type_id: int, capacity: int, price: int, max_people_per_reservation: int}>  $seats  販売する座席
     *
     * @throws BroadcastUpdateException 試合が終了しているとき、席数が予約済みの人数を下回るとき
     */
    public function update(Broadcast $broadcast, string $status, Carbon $reservationStartAt, Carbon $reservationEndAt, array $seats): void
    {
        DB::transaction(function () use ($broadcast, $status, $reservationStartAt, $reservationEndAt, $seats) {
            // 同じ放映への同時予約・同時編集を直列化するため、放映の座席種別の行を ID 順にロックする
            $lockedSeats = BroadcastSeatType::query()
                ->where('broadcast_id', $broadcast->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('seat_type_id');

            // ロック後の最新の状態で、試合が終了していないかを確かめ直す
            $current = Broadcast::with('footballMatch')->findOrFail($broadcast->id);
            if ($current->footballMatch->isFinished()) {
                throw new BroadcastUpdateException(self::FINISHED_MESSAGE);
            }

            // ロック後の予約済みの人数（confirmed の人数合計）。座席の行（broadcast_seat_types.id）ごと
            $confirmedPeople = Reservation::query()
                ->confirmed()
                ->whereIn('broadcast_seat_type_id', $lockedSeats->pluck('id'))
                ->groupBy('broadcast_seat_type_id')
                ->selectRaw('broadcast_seat_type_id, SUM(number_of_people) as people')
                ->pluck('people', 'broadcast_seat_type_id');

            // ロック後の最新の状態で、座席種別が使えるかを確かめ直す（店舗の有効な座席種別か、今販売中の座席）
            $seatTypes = SeatType::query()
                ->where('shop_id', $current->shop_id)
                ->whereIn('id', collect($seats)->pluck('seat_type_id'))
                ->get()
                ->keyBy('id');
            foreach ($seats as $seat) {
                $seatType = $seatTypes->get($seat['seat_type_id']);
                $isSelling = (bool) $lockedSeats->get($seat['seat_type_id'])?->is_active;
                if ($seatType === null || ! ($seatType->is_active || $isSelling)) {
                    throw new BroadcastUpdateException(self::SEAT_TYPE_UNAVAILABLE_MESSAGE, ['seats' => self::SEAT_TYPE_UNAVAILABLE_MESSAGE]);
                }
            }

            // 公開するときは、予約できる座席（座席種別が有効なもの）が1つ以上あること。無効な座席種別の座席だけでは公開中でも予約できない
            // 非公開にするときは確認しない（座席種別がすべて無効でも、非公開にはできるようにする）
            $hasReservableSeat = collect($seats)->contains(fn (array $seat) => (bool) $seatTypes->get($seat['seat_type_id'])?->is_active);
            if ($status === Broadcast::STATUS_PUBLISHED && ! $hasReservableSeat) {
                throw new BroadcastUpdateException(self::NO_RESERVABLE_SEAT_MESSAGE, ['seats' => self::NO_RESERVABLE_SEAT_MESSAGE]);
            }

            $fieldErrors = [];
            foreach ($seats as $seat) {
                $existing = $lockedSeats->get($seat['seat_type_id']);
                $reserved = $existing === null ? 0 : (int) ($confirmedPeople[$existing->id] ?? 0);
                if ($seat['capacity'] < $reserved) {
                    $fieldErrors["seats.{$seat['seat_type_id']}.capacity"] = sprintf(self::SEAT_BELOW_RESERVED_MESSAGE, $reserved);
                }
            }
            if ($fieldErrors !== []) {
                throw new BroadcastUpdateException('席数が予約済みの人数を下回っています。', $fieldErrors);
            }

            // $fillable に頼らず、サーバー側で決めた値を明示して設定する
            $current->forceFill([
                'reservation_start_at' => $reservationStartAt,
                'reservation_end_at' => $reservationEndAt,
                'status' => $status,
            ])->save();

            $submitted = collect($seats)->keyBy('seat_type_id');

            // 既存の座席：送られたものは更新、送られなかったものは販売停止
            foreach ($lockedSeats as $seatTypeId => $existing) {
                $seat = $submitted->get($seatTypeId);
                $existing->forceFill($seat === null
                    ? ['is_active' => false]
                    : [
                        'capacity' => $seat['capacity'],
                        'price' => $seat['price'],
                        'max_people_per_reservation' => $seat['max_people_per_reservation'],
                        'is_active' => true,
                    ])->save();
            }

            // 新しく足す座席
            foreach ($submitted as $seatTypeId => $seat) {
                if ($lockedSeats->has($seatTypeId)) {
                    continue;
                }
                BroadcastSeatType::create([
                    'broadcast_id' => $broadcast->id,
                    'seat_type_id' => $seat['seat_type_id'],
                    'capacity' => $seat['capacity'],
                    'price' => $seat['price'],
                    'max_people_per_reservation' => $seat['max_people_per_reservation'],
                    'is_active' => true,
                ]);
            }
            // デッドロックになったときは、Laravel が自動で再試行する（最大3回）
        }, attempts: 3);
    }
}
