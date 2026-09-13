<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shop_id
 * @property int $football_match_id
 * @property string|null $title
 * @property string|null $reservation_start_at
 * @property string|null $reservation_end_at
 * @property string|null $doors_open_at
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BroadcastSeatType> $broadcastSeatTypes
 * @property-read int|null $broadcast_seat_types_count
 * @property-read FootballMatch $footballMatch
 * @property-read Shop|null $shop
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereDoorsOpenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereFootballMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereReservationEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereReservationStartAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereShopId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Broadcast whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Broadcast extends Model
{
    protected $fillable = [
        'shop_id',
        'football_match_id',
        'title',
        'reservation_start_at',
        'reservation_end_at',
        'doors_open_at',
        'status',
        'notes',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function footballMatch(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
    }

    public function broadcastSeatTypes(): HasMany
    {
        return $this->hasMany(BroadcastSeatType::class);
    }
}
