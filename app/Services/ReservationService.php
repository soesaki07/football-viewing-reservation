<?php

namespace App\Services;

use App\Exceptions\ReservationException;
use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    // 予約コードに使う文字（紛らわしい 0, O, 1, I を除く）
    private const CODE_CHARACTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 8;

    /**
     * 予約を確定する。金額は確定時点の価格からここで計算する。
     * トランザクション内で放映の座席種別をロックし、条件と残席を再確認してから予約を作る。
     *
     * @throws ReservationException 予約できない条件に当てはまったとき
     */
    public function reserve(User $user, Broadcast $broadcast, int $broadcastSeatTypeId, int $numberOfPeople, ?string $customerNote): Reservation
    {
        return DB::transaction(function () use ($user, $broadcast, $broadcastSeatTypeId, $numberOfPeople, $customerNote) {
            // 同じ放映への同時予約・二重送信を直列化するため、放映の座席種別の行を ID 順にロックする
            BroadcastSeatType::query()
                ->where('broadcast_id', $broadcast->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // ロック後の最新の状態で再確認する
            $broadcast = Broadcast::with(['shop', 'footballMatch'])->findOrFail($broadcast->id);
            $this->assertReservable($broadcast);

            $seatType = $broadcast->activeBroadcastSeatTypes()->where('broadcast_seat_types.id', $broadcastSeatTypeId)->first();
            if ($seatType === null) {
                throw new ReservationException('選択された座席種別は予約できません。');
            }
            if ($numberOfPeople < 1 || $numberOfPeople > $seatType->max_people_per_reservation) {
                throw new ReservationException("人数は1人以上{$seatType->max_people_per_reservation}人以下で指定してください。");
            }
            if ($numberOfPeople > $seatType->remainingSeats()) {
                throw new ReservationException('残席が足りないため予約できません。');
            }

            $alreadyReserved = Reservation::query()
                ->confirmed()
                ->where('user_id', $user->id)
                ->whereRelation('broadcastSeatType', 'broadcast_id', $broadcast->id)
                ->exists();
            if ($alreadyReserved) {
                throw new ReservationException('この放映はすでに予約済みです。');
            }

            return Reservation::create([
                'reservation_code' => $this->generateReservationCode(),
                'user_id' => $user->id,
                'broadcast_seat_type_id' => $seatType->id,
                'number_of_people' => $numberOfPeople,
                'unit_price' => $seatType->price,
                'total_price' => $seatType->price * $numberOfPeople,
                'status' => Reservation::STATUS_CONFIRMED,
                'reserved_at' => now()->utc(),
                'customer_note' => $customerNote,
            ]);
        });
    }

    /**
     * 重複しない予約コード（英大文字と数字の8桁）を発行する。
     */
    public function generateReservationCode(): string
    {
        do {
            $code = $this->randomCode();
        } while (Reservation::where('reservation_code', $code)->exists());

        return $code;
    }

    /**
     * 推測されにくい乱数で予約コード候補を作る。
     */
    protected function randomCode(): string
    {
        $code = '';
        $max = strlen(self::CODE_CHARACTERS) - 1;
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_CHARACTERS[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * 予約できない放映なら、理由を添えて例外にする。
     *
     * @throws ReservationException
     */
    private function assertReservable(Broadcast $broadcast): void
    {
        if ($broadcast->isReservable()) {
            return;
        }

        if ($broadcast->footballMatch->isFinished()) {
            throw new ReservationException('試合が終了しているため予約できません。');
        }

        throw match ($broadcast->reservationStatus()) {
            'before' => new ReservationException('予約の受付はまだ始まっていません。'),
            'closed' => new ReservationException('予約の受付は終了しました。'),
            default => new ReservationException('この放映は現在予約できません。'),
        };
    }
}
