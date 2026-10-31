<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
 * @property Carbon $kickoff_at
 * @property string $status
 * @property int|null $home_score
 * @property int|null $away_score
 * @property string|null $venue
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $last_api_synced_at
 * @property-read Team $awayTeam
 * @property-read Collection<int, Broadcast> $broadcasts
 * @property-read int|null $broadcasts_count
 * @property-read Competition $competition
 * @property-read Team $homeTeam
 *
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
 *
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

    /**
     * @return HasMany<Broadcast, $this>
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }

    /**
     * 日本時間（JST）の指定日にキックオフする試合に絞る。
     * DB は UTC 保存なので、JST の 0:00:00〜23:59:59 を UTC に変換して比較する。
     *
     * @param  Builder<FootballMatch>  $query
     */
    public function scopeKickoffOnJstDate(Builder $query, string $date): void
    {
        $start = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Tokyo')->startOfDay()->utc();
        $end = $start->copy()->addDay();

        $query->where('kickoff_at', '>=', $start)->where('kickoff_at', '<', $end);
    }

    /**
     * ホームまたはアウェイのチームの正式名・略称・3文字コードに、キーワードが部分一致する試合に絞る。
     * LIKE の特殊文字（% _）はエスケープし、入力がそのままワイルドカードにならないようにする。
     *
     * @param  Builder<FootballMatch>  $query
     */
    public function scopeInvolvingTeamLike(Builder $query, string $keyword): void
    {
        // エスケープ文字は ! を使う（MySQL と SQLite の両方で同じ書き方になる）
        $pattern = '%'.strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        $matchesKeyword = function ($teamQuery) use ($pattern) {
            $teamQuery->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("short_name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("tla LIKE ? ESCAPE '!'", [$pattern]);
        };

        $query->where(function ($group) use ($matchesKeyword) {
            $group->whereHas('homeTeam', $matchesKeyword)
                ->orWhereHas('awayTeam', $matchesKeyword);
        });
    }

    /**
     * 試合が終了したか。status が終了系、またはキックオフから3時間以上経過している場合。
     * 試合データの同期が遅れて status が古いままでも終了扱いにするため、経過時間でも判定する。
     */
    public function isFinished(): bool
    {
        return in_array($this->status, ['FINISHED', 'AWARDED', 'CANCELLED'], true)
            || $this->kickoff_at->copy()->addHours(3)->lte(now());
    }
}
