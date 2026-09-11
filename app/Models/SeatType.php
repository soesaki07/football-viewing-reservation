<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $shop_id
 * @property string $name
 * @property string|null $description
 * @property int $default_capacity
 * @property int $default_price
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BroadcastSeatType> $broadcastSeatTypes
 * @property-read int|null $broadcast_seat_types_count
 * @property-read \App\Models\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereDefaultCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereDefaultPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereShopId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SeatType extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'description',
        'default_capacity',
        'default_price',
        'is_active',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function broadcastSeatTypes(): HasMany
    {
        return $this->hasMany(BroadcastSeatType::class);
    }
}
