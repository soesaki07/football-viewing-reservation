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

    private const STATUS_LABELS = [
        'SCHEDULED' => ['label' => '開催前', 'class' => 'bg-pitch-100 text-pitch-700'],
        'TIMED' => ['label' => '開催前', 'class' => 'bg-pitch-100 text-pitch-700'],
        'IN_PLAY' => ['label' => 'LIVE', 'class' => 'animate-pulse bg-red-100 text-red-700'],
        'PAUSED' => ['label' => 'LIVE（中断中）', 'class' => 'bg-red-100 text-red-700'],
        'FINISHED' => ['label' => '終了', 'class' => 'bg-pitch-950 text-gold-400'],
        'POSTPONED' => ['label' => '延期', 'class' => 'bg-yellow-100 text-yellow-800'],
        'SUSPENDED' => ['label' => '中断', 'class' => 'bg-yellow-100 text-yellow-800'],
        'CANCELLED' => ['label' => '中止', 'class' => 'bg-gray-200 text-gray-600'],
    ];

    public function statusInfo(): array
    {
        return self::STATUS_LABELS[$this->status] ?? ['label' => $this->status, 'class' => 'bg-pitch-100 text-pitch-700'];
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
