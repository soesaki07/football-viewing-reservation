<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reservation_code
 * @property int $user_id
 * @property int $broadcast_seat_type_id
 * @property int $number_of_people
 * @property int $unit_price
 * @property int $total_price
 * @property string $status
 * @property string $reserved_at
 * @property string|null $cancelled_at
 * @property string|null $visited_at
 * @property string|null $customer_note
 * @property string|null $shop_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BroadcastSeatType $broadcastSeatType
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereBroadcastSeatTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereCustomerNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereNumberOfPeople($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereReservationCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereReservedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereShopNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reservation whereVisitedAt($value)
 * @mixin \Eloquent
 */
class Reservation extends Model
{
    protected $fillable = [
        'reservation_code',
        'user_id',
        'broadcast_seat_type_id',
        'number_of_people',
        'unit_price',
        'total_price',
        'status',
        'reserved_at',
        'cancelled_at',
        'visited_at',
        'customer_note',
        'shop_note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadcastSeatType(): BelongsTo
    {
        return $this->belongsTo(BroadcastSeatType::class);
    }
}
