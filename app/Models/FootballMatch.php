<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

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
