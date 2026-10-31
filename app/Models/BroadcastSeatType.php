<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $broadcast_id
 * @property int $seat_type_id
 * @property int $capacity
 * @property int $price
 * @property int $max_people_per_reservation
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Broadcast $broadcast
 * @property-read Collection<int, Reservation> $reservations
 * @property-read int|null $reservations_count
 * @property-read SeatType $seatType
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereBroadcastId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereMaxPeoplePerReservation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereSeatTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BroadcastSeatType whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BroadcastSeatType extends Model
{
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'seat_type_id',
        'capacity',
        'price',
        'max_people_per_reservation',
        'is_active',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function seatType(): BelongsTo
    {
        return $this->belongsTo(SeatType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * 確定済みの予約だけの一覧（残席の計算用）。
     *
     * @return HasMany<Reservation, $this>
     */
    public function confirmedReservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->confirmed();
    }

    /**
     * 残席（定員 − confirmed の予約の人数合計。0 未満にしない）。
     * withSum で confirmed_people を取得済みならそれを使い、なければ DB から数える。
     */
    public function remainingSeats(): int
    {
        // 予約が0件だと withSum の結果は null になるので、キーの有無で取得済みかを判断する
        $confirmedPeople = array_key_exists('confirmed_people', $this->attributes)
            ? $this->attributes['confirmed_people']
            : $this->confirmedReservations()->sum('number_of_people');

        return max(0, $this->capacity - (int) $confirmedPeople);
    }

    /**
     * 有効な座席種別（放映の座席種別・店舗の座席種別の両方が有効）に絞り込む。
     *
     * @param  Builder<BroadcastSeatType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('broadcast_seat_types.is_active', true)
            ->whereHas('seatType', fn (Builder $seatTypeQuery) => $seatTypeQuery->where('is_active', true));
    }
}
