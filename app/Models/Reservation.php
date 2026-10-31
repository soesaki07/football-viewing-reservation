<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reservation_code
 * @property int $user_id
 * @property int $broadcast_seat_type_id
 * @property int $number_of_people
 * @property int $unit_price
 * @property int $total_price
 * @property string $status
 * @property Carbon $reserved_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $visited_at
 * @property string|null $customer_note
 * @property string|null $shop_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BroadcastSeatType $broadcastSeatType
 * @property-read User $user
 *
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
 *
 * @mixin \Eloquent
 */
class Reservation extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_VISITED = 'visited';

    public const STATUS_NO_SHOW = 'no_show';

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'visited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadcastSeatType(): BelongsTo
    {
        return $this->belongsTo(BroadcastSeatType::class);
    }

    /**
     * 確定済み（席を押さえている）予約に絞り込む。cancelled / no_show / visited は含まない。
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * 指定した試合の放映（店舗を問わない）に対する予約に絞り込む。
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeForFootballMatch(Builder $query, int $footballMatchId): void
    {
        $query->whereRelation('broadcastSeatType.broadcast', 'football_match_id', $footballMatchId);
    }
}
