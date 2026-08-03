<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
