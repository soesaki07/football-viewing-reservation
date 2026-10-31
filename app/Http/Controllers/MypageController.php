<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MypageController extends Controller
{
    /**
     * マイページ。ログインユーザー自身の予約を、キックオフ時刻で今後・過去に分けて表示する。
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        // キックオフちょうどは今後に入れる。比較は UTC のまま行う
        $now = now()->utc();
        $upcomingReservations = $this->reservations($user)
            ->where('football_matches.kickoff_at', '>=', $now)
            ->orderBy('football_matches.kickoff_at')
            ->orderByDesc('reservations.id')
            ->get();
        $pastReservations = $this->reservations($user)
            ->where('football_matches.kickoff_at', '<', $now)
            ->orderByDesc('football_matches.kickoff_at')
            ->orderByDesc('reservations.id')
            ->get();

        $favoriteTeams = $user->favoriteTeams()->orderByRaw('COALESCE(teams.name_ja, teams.name)')->orderBy('teams.id')->get();
        $hasFavoriteTeams = $favoriteTeams->isNotEmpty();
        $showFavoriteOnly = $hasFavoriteTeams && $request->boolean('favorite', true);

        return view('mypage.index', compact(
            'user',
            'upcomingReservations',
            'pastReservations',
            'favoriteTeams',
            'hasFavoriteTeams',
            'showFavoriteOnly',
        ));
    }

    /**
     * ログインユーザーの予約を、表示に必要な関連つきで取得するクエリ。
     * キックオフ時刻で絞り込み・並び替えするため試合を join する。
     *
     * @return Builder<Reservation>
     */
    private function reservations(User $user): Builder
    {
        return Reservation::query()
            ->select('reservations.*')
            ->join('broadcast_seat_types', 'broadcast_seat_types.id', '=', 'reservations.broadcast_seat_type_id')
            ->join('broadcasts', 'broadcasts.id', '=', 'broadcast_seat_types.broadcast_id')
            ->join('football_matches', 'football_matches.id', '=', 'broadcasts.football_match_id')
            ->where('reservations.user_id', $user->id)
            ->with([
                'broadcastSeatType.seatType',
                'broadcastSeatType.broadcast.footballMatch.homeTeam',
                'broadcastSeatType.broadcast.footballMatch.awayTeam',
                // 店舗が後から削除されても表示できるよう、削除済みも読み込む
                'broadcastSeatType.broadcast.shop' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
            ]);
    }
}
