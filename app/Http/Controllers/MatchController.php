<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(Request $request): View
    {
        $competitionCode = $request->input('competition', 'PL');
        $matchDay = (int) $request->input('match_day', 1);
        $stage = $request->input('stage', '');

        $competition = Competition::where('code', $competitionCode)->first();

        $footballMatches = collect();
        $matchDays = collect();
        $stages = collect();
        $isRegularSeasonOnly = true;
        if ($competition) {
            $isRegularSeasonOnly = FootballMatch::where('competition_id', $competition->id)
                ->where('stage', '!=', 'REGULAR_SEASON')
                ->doesntExist();

            if ($isRegularSeasonOnly) {
                $matchDays = FootballMatch::where('competition_id', $competition->id)
                    ->whereNotNull('match_day')
                    ->distinct()
                    ->orderBy('match_day')
                    ->pluck('match_day');

                $footballMatches = FootballMatch::with(['homeTeam', 'awayTeam'])
                    ->where('competition_id', $competition->id)
                    ->where('match_day', $matchDay)
                    ->oldest('kickoff_at')
                    ->get();
            } else {
                $stages = FootballMatch::where('competition_id', $competition->id)
                    ->distinct()
                    ->pluck('stage');

                if (! $request->filled('stage')) {
                    $stage = $stages->first() ?? '';
                }

                $footballMatches = FootballMatch::with(['homeTeam', 'awayTeam'])
                    ->where('competition_id', $competition->id)
                    ->where('stage', $stage)
                    ->oldest('kickoff_at')
                    ->get();
            }
        }

        $competitions = Competition::orderBy('name')->get();

        /** @var User $user */
        $user = Auth::user();
        $favoriteTeams = $user->favoriteTeams;
        $favoriteTeamIds = $favoriteTeams->pluck('id');

        $favoriteTeamMatches = $favoriteTeams->map(function (Team $favoriteTeam) {
            $recentMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'competition'])
                ->where(function ($query) use ($favoriteTeam) {
                    $query->where('home_team_id', $favoriteTeam->id)
                        ->orWhere('away_team_id', $favoriteTeam->id);
                })
                ->where('kickoff_at', '<', now())
                ->latest('kickoff_at')   // 直近の過去から降順
                ->limit(2)
                ->get()
                ->sortBy('kickoff_at');  // 表示用に古い→新しいの順に並べ直す

            $upcomingMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'competition'])
                ->where(function ($query) use ($favoriteTeam) {
                    $query->where('home_team_id', $favoriteTeam->id)
                        ->orWhere('away_team_id', $favoriteTeam->id);
                })
                ->where('kickoff_at', '>=', now())
                ->oldest('kickoff_at')   // 近い未来から昇順
                ->limit(3)
                ->get();

            return [
                'team' => $favoriteTeam,
                'recentMatches' => $recentMatches,
                'upcomingMatches' => $upcomingMatches,
            ];
        });

        $hasFavoriteTeams = $favoriteTeamIds->isNotEmpty();

        $showFavoriteOnly = $hasFavoriteTeams && $request->boolean('favorite', true);

        return view('matches.index', compact(
            'footballMatches',
            'competitions',
            'competitionCode',
            'matchDay',
            'matchDays',
            'stage',
            'stages',
            'isRegularSeasonOnly',
            'favoriteTeamMatches',
            'showFavoriteOnly',
            'hasFavoriteTeams',
            'favoriteTeams',
        ));
    }

    public function show(Request $request, int $id): View
    {
        $footballMatch = FootballMatch::with(['competition', 'homeTeam', 'awayTeam'])
            ->findOrFail($id);

        /** @var User $user */
        $user = Auth::user();
        $favoriteTeamIds = $user->favoriteTeams->pluck('id');
        $hasFavoriteTeams = $favoriteTeamIds->isNotEmpty();
        $showFavoriteOnly = $hasFavoriteTeams && $request->boolean('favorite', true);

        $isFinished = $footballMatch->isFinished();
        $sort = in_array($request->query('sort'), self::BROADCAST_SORTS, true)
            ? $request->query('sort')
            : self::BROADCAST_SORTS[0];
        $broadcastShops = $isFinished ? collect() : $this->broadcastShops($footballMatch, $sort);

        return view('matches.show', compact('footballMatch', 'hasFavoriteTeams', 'showFavoriteOnly', 'isFinished', 'broadcastShops', 'sort'));
    }

    /**
     * 放映店舗一覧の並び順の許可リスト（先頭が既定）。
     */
    private const BROADCAST_SORTS = ['seats', 'price', 'name'];

    /**
     * 試合を放映する店舗の一覧（1店舗＝1行）を並び順どおりに返す。
     * 放映・店舗・座席種別・予約の合計をまとめて取得し、店舗数が増えてもクエリ数が増えないようにする。
     *
     * @return Collection<int, array{shop: Shop, minPrice: int, seats: int, reservationStatus: string|null}>
     */
    private function broadcastShops(FootballMatch $footballMatch, string $sort): Collection
    {
        $broadcasts = Broadcast::query()
            ->where('football_match_id', $footballMatch->id)
            ->published()
            ->whereRelation('shop', 'status', Shop::STATUS_PUBLISHED)
            ->with([
                'shop',
                'activeBroadcastSeatTypes' => fn ($query) => $query->withSum('confirmedReservations as confirmed_people', 'number_of_people'),
            ])
            ->get();

        $rows = collect();
        foreach ($broadcasts as $broadcast) {
            $shop = $broadcast->shop;
            // 有効な座席種別がない放映は出さない（店舗は whereHas で公開中のものに絞り込み済み）
            if ($shop === null || $broadcast->activeBroadcastSeatTypes->isEmpty()) {
                continue;
            }

            $rows->push([
                'shop' => $shop,
                'minPrice' => (int) $broadcast->activeBroadcastSeatTypes->min('price'),
                // 定員を超えた予約データがあっても、残席はマイナスにしない
                'seats' => $broadcast->activeBroadcastSeatTypes->sum(
                    fn (BroadcastSeatType $seatType) => max(0, $seatType->capacity - (int) $seatType->getAttribute('confirmed_people'))
                ),
                'reservationStatus' => $this->reservationStatus($broadcast),
            ]);
        }

        // 同順位は店舗名 → 店舗 ID の順で安定させる
        return $rows->sort(function (array $a, array $b) use ($sort) {
            $primary = match ($sort) {
                'price' => $a['minPrice'] <=> $b['minPrice'],
                'name' => 0,
                default => $b['seats'] <=> $a['seats'],
            };

            return $primary ?: ($a['shop']->name <=> $b['shop']->name) ?: ($a['shop']->id <=> $b['shop']->id);
        })->values();
    }

    /**
     * 予約の受付状態。開始前は 'before'、終了後は 'closed'、期間内（null は制限なし）は null。
     */
    private function reservationStatus(Broadcast $broadcast): ?string
    {
        $now = now();

        if ($broadcast->reservation_start_at !== null && Carbon::parse($broadcast->reservation_start_at)->gt($now)) {
            return 'before';
        }
        if ($broadcast->reservation_end_at !== null && Carbon::parse($broadcast->reservation_end_at)->lt($now)) {
            return 'closed';
        }

        return null;
    }
}
