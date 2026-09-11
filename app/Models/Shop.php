<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $postal_code
 * @property string|null $prefecture
 * @property string|null $city
 * @property string $address_line
 * @property numeric|null $latitude
 * @property numeric|null $longitude
 * @property string|null $google_place_id
 * @property string|null $phone_number
 * @property string|null $description
 * @property string|null $opening_time
 * @property string|null $closing_time
 * @property string|null $website_url
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Broadcast> $broadcasts
 * @property-read int|null $broadcasts_count
 * @property-read \App\Models\User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SeatType> $seatTypes
 * @property-read int|null $seat_types_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereAddressLine($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereClosingTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereGooglePlaceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereOpeningTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop wherePrefecture($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereWebsiteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop withoutTrashed()
 * @mixin \Eloquent
 */
class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'postal_code',
        'prefecture',
        'city',
        'address_line',
        'latitude',
        'longitude',
        'google_place_id',
        'phone_number',
        'description',
        'opening_time',
        'closing_time',
        'website_url',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seatTypes(): HasMany
    {
        return $this->hasMany(SeatType::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }
}
