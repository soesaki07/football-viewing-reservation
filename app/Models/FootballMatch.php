<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property int $external_match_id
 * @property int $competition_id
 * @property int $home_team_id
 * @property int $away_team_id
 * @property int|null $season_start_year
 * @property int|null $match_day
 * @property string|null $stage
 * @property \Illuminate\Support\Carbon $kickoff_at
 * @property string $status
 * @property int|null $home_score
 * @property int|null $away_score
 * @property string|null $venue
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $last_api_synced_at
 * @property-read \App\Models\Team $awayTeam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Broadcast> $broadcasts
 * @property-read int|null $broadcasts_count
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Team $homeTeam
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereAwayScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereAwayTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereExternalMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereHomeScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereHomeTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereKickoffAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereLastApiSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereMatchDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereSeasonStartYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FootballMatch whereVenue($value)
 * @mixin \Eloquent
 */
class FootballMatch extends Model
{
    protected $fillable = [
        'external_match_id',
        'competition_id',
        'home_team_id',
        'away_team_id',
        'season_start_year',
        'match_day',
        'stage',
        'kickoff_at',
        'status',
        'home_score',
        'away_score',
        'venue',
        'last_api_synced_at',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'last_api_synced_at' => 'datetime',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }
}
