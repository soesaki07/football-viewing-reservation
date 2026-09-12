<?php

namespace App\Http\Controllers;

use App\Http\Requests\FavoriteTeamRequest;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FavoriteTeamController extends Controller
{
    public function selectFavoriteTeams(Request $request): View
    {
        $user = Auth::user();
        $competitions = Competition::all();
        $competitionCode = $request->input('competition', 'PL');

        $teamCompetitionCodes = [];

        $competitionIds = $competitions->pluck('id');
        $matchesByCompetition = FootballMatch::whereIn('competition_id', $competitionIds)
            ->get()
            ->groupBy('competition_id');
        foreach ($competitions as $competition) {
            $matches = $matchesByCompetition->get($competition->id, collect());
            $teamIds = $matches->pluck('home_team_id')
                ->merge($matches->pluck('away_team_id'))
                ->unique();

            foreach ($teamIds as $teamId) {
                $teamCompetitionCodes[$teamId][] = $competition->code;
            }
        }

        $teams = Team::whereIn('id', array_keys($teamCompetitionCodes))->orderBy('name')->get();

        return view('favorite.selectTeams', compact('teams', 'competitions', 'user', 'competitionCode', 'teamCompetitionCodes'));
    }

    public function saveFavoriteTeams(FavoriteTeamRequest $request)
    {
        $validated = $request->validated();
        /** @var User $user */
        $user = Auth::user();
        $user->favoriteTeams()
            ->sync($validated['team_ids'] ?? []);

        return redirect(route('matches.index'))->with('success', 'お気に入り登録完了しました。');
    }
}
