<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
