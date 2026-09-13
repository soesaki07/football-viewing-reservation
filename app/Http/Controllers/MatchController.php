<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
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

        return view('matches.show', compact('footballMatch', 'hasFavoriteTeams', 'showFavoriteOnly'));
    }
}
