<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $external_competition_id
 * @property string $code
 * @property string|null $type
 * @property string $name
 * @property string|null $area_name
 * @property string|null $emblem_url
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FootballMatch> $footballMatches
 * @property-read int|null $football_matches_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereAreaName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereEmblemUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereExternalCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Competition extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_competition_id',
        'code',
        'type',
        'name',
        'area_name',
        'emblem_url',
        'is_active',
        'type',
    ];

    public function footballMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }
}
