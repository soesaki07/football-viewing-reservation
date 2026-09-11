<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $broadcast_id
 * @property int $seat_type_id
 * @property int $capacity
 * @property int $price
 * @property int $max_people_per_reservation
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Broadcast $broadcast
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservation> $reservations
 * @property-read int|null $reservations_count
 * @property-read \App\Models\SeatType $seatType
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
 * @mixin \Eloquent
 */
class BroadcastSeatType extends Model
{
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
}
