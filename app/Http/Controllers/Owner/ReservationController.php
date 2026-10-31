<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReservationController extends Controller
{
    private const PERIOD_UPCOMING = 'upcoming';

    private const PERIOD_PAST = 'past';

    private const PER_PAGE = 20;

    /**
     * 店舗オーナーの予約一覧。選択中の店舗の予約を、今後・過去のタブと試合で絞り込んで表示する。
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $hasFavoriteTeams = $user->favoriteTeams->isNotEmpty();
        $showFavoriteOnly = $hasFavoriteTeams && $request->boolean('favorite', true);

        // 自分の店舗（削除済みは SoftDeletes で除かれる。非公開も含む）
        $shops = Shop::query()->where('user_id', $user->id)->orderBy('id')->get();

        if ($shops->isEmpty()) {
            return view('owner.reservations.index', compact('shops', 'hasFavoriteTeams', 'showFavoriteOnly'));
        }

        // ?shop= の指定があれば、存在しない・削除済みは 404、他のオーナーの店舗は Policy で 404
        if ($request->filled('shop')) {
            $shop = Shop::query()->findOrFail($request->integer('shop'));
        } else {
            $shop = $shops->firstOrFail();
        }
        Gate::authorize('viewReservations', $shop);

        $period = $request->query('period') === self::PERIOD_PAST ? self::PERIOD_PAST : self::PERIOD_UPCOMING;
        $selectedMatchId = $request->filled('match') ? $request->integer('match') : null;

        // キックオフちょうどは今後に入れる。比較は UTC のまま行う
        $now = now()->utc();
        $isUpcoming = $period === self::PERIOD_UPCOMING;

        // 試合の絞り込みの選択肢：この店舗が放映する試合のうち、選択中のタブに当てはまるもの
        $matchOptions = FootballMatch::query()
            ->whereHas('broadcasts', fn (Builder $query) => $query->where('shop_id', $shop->id))
            ->where('kickoff_at', $isUpcoming ? '>=' : '<', $now)
            ->orderBy('kickoff_at', $isUpcoming ? 'asc' : 'desc')
            ->orderBy('id')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $reservations = Reservation::query()
            ->select('reservations.*')
            ->join('broadcast_seat_types', 'broadcast_seat_types.id', '=', 'reservations.broadcast_seat_type_id')
            ->join('broadcasts', 'broadcasts.id', '=', 'broadcast_seat_types.broadcast_id')
            ->join('football_matches', 'football_matches.id', '=', 'broadcasts.football_match_id')
            // 必ず認可済みの店舗の放映に限定する（試合の指定だけで他店の予約が出ないように）
            ->where('broadcasts.shop_id', $shop->id)
            ->where('football_matches.kickoff_at', $isUpcoming ? '>=' : '<', $now)
            ->when($selectedMatchId !== null, fn (Builder $query) => $query->where('football_matches.id', $selectedMatchId))
            ->orderBy('football_matches.kickoff_at', $isUpcoming ? 'asc' : 'desc')
            ->orderByDesc('reservations.id')
            ->with([
                // ユーザーは名前だけ取得する（メールアドレスなどは扱わない）
                'user:id,name',
                'broadcastSeatType.seatType',
                'broadcastSeatType.broadcast.footballMatch.homeTeam',
                'broadcastSeatType.broadcast.footballMatch.awayTeam',
            ])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('owner.reservations.index', compact(
            'shops',
            'shop',
            'period',
            'selectedMatchId',
            'matchOptions',
            'reservations',
            'hasFavoriteTeams',
            'showFavoriteOnly',
        ));
    }
}
