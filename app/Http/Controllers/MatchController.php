<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\FootballMatch;
use Illuminate\Http\Request;
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

        return view('matches.index', compact(
            'footballMatches',
            'competitions',
            'competitionCode',
            'matchDay',
            'matchDays',
            'stage',
            'stages',
            'isRegularSeasonOnly',
        ));
    }

    public function show(int $id): View
    {
        $footballMatch = FootballMatch::with(['competition', 'homeTeam', 'awayTeam'])
            ->findOrFail($id);

        return view('matches.show', compact('footballMatch'));
    }
}
