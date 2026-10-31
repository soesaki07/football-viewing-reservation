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
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

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

    /**
     * 有効な座席種別（放映の座席種別・店舗の座席種別の両方が有効）だけの一覧。
     *
     * @return HasMany<BroadcastSeatType, $this>
     */
    public function activeBroadcastSeatTypes(): HasMany
    {
        return $this->hasMany(BroadcastSeatType::class)->active();
    }

    /**
     * 公開中の放映に絞り込む。
     *
     * @param  Builder<Broadcast>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * 予約の受付状態。開始前は 'before'、終了後は 'closed'、期間内（null は制限なし）は null。
     */
    public function reservationStatus(): ?string
    {
        $now = now();

        if ($this->reservation_start_at !== null && Carbon::parse($this->reservation_start_at)->gt($now)) {
            return 'before';
        }
        if ($this->reservation_end_at !== null && Carbon::parse($this->reservation_end_at)->lt($now)) {
            return 'closed';
        }

        return null;
    }

    /**
     * 予約できる放映か。放映・店舗が公開中（店舗は削除されていない）、試合が終了していない、受付期間中の全てを満たす。
     * 座席種別・残席・二重予約はここでは見ない。
     */
    public function isReservable(): bool
    {
        $shop = $this->shop;

        return $this->status === self::STATUS_PUBLISHED
            && $shop !== null
            && $shop->isPublished()
            && ! $this->footballMatch->isFinished()
            && $this->reservationStatus() === null;
    }
}
