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

        $competition = Competition::where('code', $competitionCode)->first();

        $footballMatches = collect();
        $matchDays = collect();

        if ($competition) {
            $matchDays = FootballMatch::where('competition_id', $competition->id)
                ->whereNotNull('match_day')
                ->distinct()
                ->orderBy('match_day')
                ->pluck('match_day');

            $footballMatches = FootballMatch::with(['homeTeam', 'awayTeam'])
                ->where('competition_id', $competition->id)
                ->where('match_day', $matchDay)
                ->latest('kickoff_at')
                ->get();
        }

        $competitions = Competition::orderBy('name')->get();

        return view('matches.index', compact(
            'footballMatches',
            'competitions',
            'competitionCode',
            'matchDay',
            'matchDays',
        ));
    }
}
