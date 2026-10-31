<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $external_team_id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $name_ja
 * @property string|null $short_name_ja
 * @property string|null $tla
 * @property string|null $crest_url
 * @property string|null $country_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FootballMatch> $awayMatches
 * @property-read int|null $away_matches_count
 * @property-read Collection<int, User> $favoritedByUsers
 * @property-read int|null $favorited_by_users_count
 * @property-read Collection<int, FootballMatch> $homeMatches
 * @property-read int|null $home_matches_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCountryName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCrestUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereExternalTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereNameJa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereShortNameJa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereTla($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_team_id',
        'name',
        'short_name',
        'name_ja',
        'short_name_ja',
        'tla',
        'crest_url',
        'country_name',
    ];

    public function homeMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'away_team_id');
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_teams')->withTimestamps();
    }

    /**
     * 画面に出すチーム名。日本語名があれば日本語名、なければ英語名。
     */
    public function displayName(): string
    {
        return $this->name_ja ?? $this->name;
    }

    /**
     * 画面に出す略称。日本語の略称、API の略称、チーム名（日本語優先）の順。
     */
    public function displayShortName(): string
    {
        return $this->short_name_ja ?? $this->short_name ?? $this->displayName();
    }
}
