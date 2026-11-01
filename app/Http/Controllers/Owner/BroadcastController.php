<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBroadcastRequest;
use App\Models\Broadcast;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Shop;
use App\Models\User;
use App\Services\BroadcastService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    private const PERIOD_UPCOMING = 'upcoming';

    private const PERIOD_PAST = 'past';

    private const PER_PAGE = 20;

    /**
     * 店舗オーナーの放映一覧。選択中の店舗の放映を、今後・過去のタブで表示する。
     */
    public function index(Request $request): View
    {
        [$shops, $shop] = $this->resolveShop($request);
        $headerData = $this->headerData($request);

        if ($shop === null) {
            return view('owner.broadcasts.index', compact('shops') + $headerData);
        }

        $period = $request->query('period') === self::PERIOD_PAST ? self::PERIOD_PAST : self::PERIOD_UPCOMING;

        // キックオフちょうどは今後に入れる。比較は UTC のまま行う
        $now = now()->utc();
        $isUpcoming = $period === self::PERIOD_UPCOMING;

        $broadcasts = Broadcast::query()
            ->select('broadcasts.*')
            ->join('football_matches', 'football_matches.id', '=', 'broadcasts.football_match_id')
            // 必ず認可済みの店舗の放映に限定する
            ->where('broadcasts.shop_id', $shop->id)
            ->where('football_matches.kickoff_at', $isUpcoming ? '>=' : '<', $now)
            ->orderBy('football_matches.kickoff_at', $isUpcoming ? 'asc' : 'desc')
            ->orderBy('broadcasts.id')
            ->with([
                'footballMatch.homeTeam',
                'footballMatch.awayTeam',
                // 予約数（confirmed の人数合計）を座席ごとにまとめて取得する（N+1 を避ける）
                'broadcastSeatTypes' => fn ($query) => $query
                    ->withSum('confirmedReservations as confirmed_people', 'number_of_people')
                    ->orderBy('id'),
                'broadcastSeatTypes.seatType',
            ])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('owner.broadcasts.index', compact('shops', 'shop', 'period', 'broadcasts') + $headerData);
    }

    /**
     * 放映の登録画面。大会 → 試合の順に選ぶと、受付期間・座席の入力欄を表示する。
     */
    public function create(Request $request): View
    {
        [$shops, $shop] = $this->resolveShop($request);
        $headerData = $this->headerData($request);

        if ($shop === null) {
            return view('owner.broadcasts.create', compact('shops') + $headerData);
        }

        // 販売できる座席種別（店舗の有効なもの）
        $seatTypes = $shop->seatTypes()->where('is_active', true)->orderBy('id')->get();

        $now = now()->utc();

        // 大会の選択肢：キックオフ前の試合がある大会
        $competitions = Competition::query()
            ->whereHas('footballMatches', fn (Builder $query) => $query->where('kickoff_at', '>', $now)->whereNotIn('status', FootballMatch::FINISHED_STATUSES))
            ->orderBy('id')
            ->get();

        $competitionId = $request->filled('competition') ? $request->integer('competition') : null;

        // 試合の選択肢：選んだ大会のキックオフ前の試合のうち、この店舗で未登録のもの
        $matchOptions = collect();
        if ($competitionId !== null) {
            $matchOptions = FootballMatch::query()
                ->where('competition_id', $competitionId)
                ->where('kickoff_at', '>', $now)
                ->whereNotIn('status', FootballMatch::FINISHED_STATUSES)
                ->whereDoesntHave('broadcasts', fn (Builder $query) => $query->where('shop_id', $shop->id))
                ->orderBy('kickoff_at')
                ->orderBy('id')
                ->with(['homeTeam', 'awayTeam'])
                ->get();
        }

        // 選択肢にある試合だけを選択済みとして扱う
        $selectedMatch = $request->filled('match') ? $matchOptions->firstWhere('id', $request->integer('match')) : null;

        return view('owner.broadcasts.create', compact('shops', 'shop', 'seatTypes', 'competitions', 'competitionId', 'matchOptions', 'selectedMatch') + $headerData);
    }

    /**
     * 放映を登録する。入力の検証・認可は StoreBroadcastRequest で行う。
     */
    public function store(StoreBroadcastRequest $request, BroadcastService $broadcastService): RedirectResponse
    {
        $shop = $request->shop();
        $validated = $request->validated();

        try {
            $broadcastService->create(
                $shop,
                (int) $validated['football_match_id'],
                $validated['status'],
                $request->parseJst($validated['reservation_start_at']),
                $request->parseJst($validated['reservation_end_at']),
                $request->seatsForStore(),
            );
        } catch (UniqueConstraintViolationException) {
            // 同時に同じ試合を登録されたとき。500 にせず入力画面へ戻す
            $competitionId = FootballMatch::query()->whereKey($validated['football_match_id'])->value('competition_id');

            return redirect()
                ->route('owner.broadcasts.create', ['shop' => $shop->id, 'competition' => $competitionId, 'match' => $validated['football_match_id']])
                ->withInput()
                ->withErrors(['football_match_id' => BroadcastService::ALREADY_REGISTERED_MESSAGE]);
        }

        return redirect()
            ->route('owner.broadcasts.index', ['shop' => $shop->id])
            ->with('success', '放映を登録しました。');
    }

    /**
     * ヘッダー（レイアウト）が使う、お気に入りチームの有無と絞り込み状態。
     *
     * @return array{hasFavoriteTeams: bool, showFavoriteOnly: bool}
     */
    private function headerData(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $hasFavoriteTeams = $user->favoriteTeams->isNotEmpty();

        return [
            'hasFavoriteTeams' => $hasFavoriteTeams,
            'showFavoriteOnly' => $hasFavoriteTeams && $request->boolean('favorite', true),
        ];
    }

    /**
     * 自分の店舗の一覧と、選択中の店舗を決める。店舗が0件なら選択中は null。
     * ?shop= の指定があれば、存在しない・削除済み・他のオーナーの店舗は 404。
     *
     * @return array{0: Collection<int, Shop>, 1: Shop|null}
     */
    private function resolveShop(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        // 自分の店舗（削除済みは SoftDeletes で除かれる。非公開も含む）
        $shops = Shop::query()->where('user_id', $user->id)->orderBy('id')->get();

        if ($shops->isEmpty()) {
            return [$shops, null];
        }

        if ($request->filled('shop')) {
            // 配列などスカラーでない値は、存在しない店舗と同じく 404
            abort_unless(is_scalar($request->query('shop')), 404);
            // 自分の店舗の中から探す（他人の店舗・存在しない店舗は 404）
            $shop = $shops->firstWhere('id', $request->integer('shop'));
            abort_if($shop === null, 404);
        } else {
            $shop = $shops->firstOrFail();
        }
        Gate::authorize('manageBroadcasts', $shop);

        return [$shops, $shop];
    }
}
