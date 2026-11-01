<?php

namespace App\Services;

use App\Exceptions\ReservationException;
use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    // 予約コードに使う文字（紛らわしい 0, O, 1, I を除く）
    private const CODE_CHARACTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 8;

    public const ALREADY_RESERVED_MESSAGE = 'この試合はすでに予約済みです。';

    public const NOT_CANCELLABLE_MESSAGE = 'この予約はキャンセルできません。';

    public const NOT_VISIT_STATUS_CHANGEABLE_MESSAGE = 'この予約の来店状態は変更できません。';

    // 予約コードが重複したときに、新しいコードで作り直す最大回数
    private const MAX_CODE_ATTEMPTS = 3;

    /**
     * 予約を確定する。金額は確定時点の価格からここで計算する。
     * トランザクション内でユーザー → 放映の座席種別の順にロックし、条件と残席を再確認してから予約を作る。
     *
     * 注意：
     * - 同じトランザクションの中で、ロックより前に普通の SELECT をしないこと。
     *   MySQL の REPEATABLE READ では最初の SELECT の時点の状態が見え続けるため、
     *   ロックを待ったあとの再確認が古い状態になり、同時確定で2件目の予約ができてしまう。
     * - 外側のトランザクションの中から呼ばないこと（呼び出し前に SELECT 済みの状態を引き継ぐ可能性があり、上と同じ問題になる）。
     *
     * @throws ReservationException 予約できない条件に当てはまったとき
     */
    public function reserve(User $user, Broadcast $broadcast, int $broadcastSeatTypeId, int $numberOfPeople, ?string $customerNote): Reservation
    {
        return DB::transaction(function () use ($user, $broadcast, $broadcastSeatTypeId, $numberOfPeople, $customerNote) {
            // 同じユーザーが同じ試合の別の店舗へ同時に予約するのを直列化するため、ユーザーの行を先にロックする
            // （ロックの順番は常に「users → broadcast_seat_types」で固定し、デッドロックを避ける）
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

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

            if ($this->hasReservedMatch($user, $broadcast->football_match_id)) {
                throw new ReservationException(self::ALREADY_RESERVED_MESSAGE);
            }

            return $this->createReservation([
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
     * 予約コードを付けて予約を作る。コードが重複（UNIQUE 違反）したら、新しいコードで最大3回まで作り直す。
     * reservations の UNIQUE 制約は reservation_code だけなので、UNIQUE 違反は予約コードの重複とみなす。
     * 入れ子の DB::transaction()（セーブポイント）で失敗した INSERT だけを取り消し、
     * 外側のトランザクションとロックは保ったまま、作成だけを再試行する。
     *
     * @param  array<string, mixed>  $attributes  予約コード以外の属性
     *
     * @throws ReservationException 3回とも予約コードが重複したとき
     */
    private function createReservation(array $attributes): Reservation
    {
        for ($attempt = 1; $attempt <= self::MAX_CODE_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(fn () => Reservation::create([
                    'reservation_code' => $this->generateReservationCode(),
                    ...$attributes,
                ]));
            } catch (UniqueConstraintViolationException) {
                // 次の試行で新しいコードを発行する
            }
        }

        // 予約コードそのものや個人情報は出さず、調査に必要な ID だけ記録する
        Log::warning('予約コードの発行に3回失敗しました。', [
            'attempts' => self::MAX_CODE_ATTEMPTS,
            'user_id' => $attributes['user_id'] ?? null,
            'broadcast_seat_type_id' => $attributes['broadcast_seat_type_id'] ?? null,
        ]);

        throw new ReservationException('予約コードの発行に失敗しました。もう一度お試しください。');
    }

    /**
     * 予約をキャンセルする。予約の行だけをロックし、ロック後の最新の状態でキャンセルできるかを再確認する。
     * 予約の人数・金額などは変えず、status と cancelled_at だけを更新する（残席は confirmed の人数合計から自動で戻る）。
     *
     * @throws ReservationException キャンセルできない予約のとき
     */
    public function cancel(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            // 二重送信・同時操作を直列化するため、予約の行をロックして読み直す
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            // isCancellable() が使う関連を、ロックの後に明示的に読み込む（遅延読み込みに頼らない）
            $locked->load('broadcastSeatType.broadcast.footballMatch');

            if (! $locked->isCancellable()) {
                throw new ReservationException(self::NOT_CANCELLABLE_MESSAGE);
            }

            $locked->update([
                'status' => Reservation::STATUS_CANCELLED,
                'cancelled_at' => now()->utc(),
            ]);

            return $locked;
        });
    }

    /**
     * 予約の来店状態（visited / no_show）を更新する。予約の行だけをロックし、ロック後の最新の状態で変更できるかを再確認する。
     * status と visited_at だけを更新する（visited のときは現在時刻、no_show のときは NULL）。
     *
     * @throws ReservationException 変更できない予約のとき
     */
    public function updateVisitStatus(Reservation $reservation, string $status): Reservation
    {
        return DB::transaction(function () use ($reservation, $status) {
            // キャンセル・二重送信との同時操作を直列化するため、予約の行をロックして読み直す
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            // canChangeVisitStatusTo() が使う関連を、ロックの後に明示的に読み込む
            $locked->load('broadcastSeatType.broadcast.footballMatch');

            if (! $locked->canChangeVisitStatusTo($status)) {
                throw new ReservationException(self::NOT_VISIT_STATUS_CHANGEABLE_MESSAGE);
            }

            $locked->update([
                'status' => $status,
                'visited_at' => $status === Reservation::STATUS_VISITED ? now()->utc() : null,
            ]);

            return $locked;
        });
    }

    /**
     * ユーザーが、この試合（どの店舗でも）に confirmed の予約を持っているか。
     */
    public function hasReservedMatch(User $user, int $footballMatchId): bool
    {
        return Reservation::query()
            ->confirmed()
            ->where('user_id', $user->id)
            ->forFootballMatch($footballMatchId)
            ->exists();
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
