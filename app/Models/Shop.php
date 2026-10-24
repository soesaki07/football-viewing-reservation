<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Broadcast> $broadcasts
 * @property-read int|null $broadcasts_count
 * @property-read User $owner
 * @property-read Collection<int, SeatType> $seatTypes
 * @property-read int|null $seat_types_count
 *
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
 *
 * @mixin \Eloquent
 */
class Shop extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PUBLISHED = 'published';

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

    /**
     * 公開中の店舗に絞り込む。
     *
     * @param  Builder<Shop>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * 都道府県・市区町村・番地をつなげた住所（郵便番号は含まない）。
     */
    public function fullAddress(): string
    {
        return $this->prefecture.$this->city.$this->address_line;
    }

    /**
     * 営業時間を「17:00〜翌1:00」の形式で返す。どちらかが未設定なら null。
     * DB の値は JST の時刻なので、タイムゾーンの変換はしない。
     */
    public function businessHoursLabel(): ?string
    {
        if ($this->opening_time === null || $this->closing_time === null) {
            return null;
        }

        $opening = Carbon::parse($this->opening_time);
        $closing = Carbon::parse($this->closing_time);
        // 閉店時刻が開店時刻より前なら、日をまたいで営業している
        $nextDay = $closing->format('H:i') < $opening->format('H:i') ? '翌' : '';

        return $opening->format('G:i').'〜'.$nextDay.$closing->format('G:i');
    }

    /**
     * Web サイトの URL がリンクにしてよい形式（http / https）か。
     * javascript: などのスキームをリンクにしないための確認。
     */
    public function hasSafeWebsiteUrl(): bool
    {
        if ($this->website_url === null) {
            return false;
        }

        $scheme = parse_url($this->website_url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    /**
     * Maps Embed API の URL。場所は place_id、緯度経度、住所の順に、使えるものを指定する。
     */
    public function googleMapsEmbedUrl(string $apiKey): string
    {
        if ($this->google_place_id !== null) {
            $query = 'place_id:'.$this->google_place_id;
        } elseif ($this->latitude !== null && $this->longitude !== null) {
            $query = $this->latitude.','.$this->longitude;
        } else {
            $query = $this->fullAddress();
        }

        return 'https://www.google.com/maps/embed/v1/place?'.http_build_query([
            'key' => $apiKey,
            'q' => $query,
        ]);
    }

    /**
     * API キーが不要な、Google マップで店舗を開くリンクの URL。
     */
    public function googleMapsSearchUrl(): string
    {
        $params = [
            'api' => 1,
            'query' => $this->latitude !== null && $this->longitude !== null
                ? $this->latitude.','.$this->longitude
                : $this->fullAddress(),
        ];
        if ($this->google_place_id !== null) {
            $params['query_place_id'] = $this->google_place_id;
        }

        return 'https://www.google.com/maps/search/?'.http_build_query($params);
    }
}
