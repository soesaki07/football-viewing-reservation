<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MatchController extends Controller
{
    /**
     * 検索結果のページ番号の上限（巨大な値で offset の計算が int の範囲を超えて 500 になるのを防ぐ）。
     */
    private const MAX_SEARCH_PAGE = 10000;

    /**
     * 放映店舗一覧の並び順の許可リスト（先頭が既定）。
     */
    private const BROADCAST_SORTS = ['seats', 'price', 'name'];

    public function index(Request $request): View
    {
        // 検索（日付・チーム名）。入力が不正なら検索せず、エラーを画面に出す
        // 大会の存在チェックは検索のとき（日付かチーム名があるとき）だけ行う。検索なしの存在しない大会は、従来どおり「見つかりませんでした」の表示にする
        // filled() は配列（team[]=a など）も入力ありとみなすが、date・team は下の date_format・string ルールで配列を弾くので、配列で検索が実行されることはない
        $isSearchInput = $request->filled('date') || $request->filled('team');
        $searchValidator = Validator::make($request->query(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'team' => ['nullable', 'string', 'max:50', function (string $attribute, mixed $value, Closure $fail) {
                // 不正なバイト列（UTF-8 でない文字列）は検索に使わない
                if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                    $fail('チーム名に使えない文字が含まれています。');
                }
            }],
            'competition' => ['bail', 'nullable', 'string', Rule::when($isSearchInput, [
                function (string $attribute, mixed $value, Closure $fail) {
                    // 不正なバイト列は DB に渡さず、存在しない大会として扱う（先に弾く）
                    if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                        $fail('選択した大会は存在しません。');
                    }
                },
                'exists:competitions,code',
            ])],
        ], [
            'date.date_format' => '日付は YYYY-MM-DD の形式で正しい日付を指定してください。',
            'team.max' => 'チーム名は50文字以内で入力してください。',
            'team.string' => 'チーム名は文字列で入力してください。',
            'competition.exists' => '選択した大会は存在しません。',
        ]);
        $searchErrors = $searchValidator->errors();
        $isSearching = false;
        $searchResults = null;
        $searchParams = ['date' => '', 'team' => '', 'competition' => '', 'period' => 'upcoming'];
        $showPeriodTabs = false;

        if ($searchValidator->fails()) {
            // 入力エラーのときは、入力した値をフォームに残す（文字列でない値・UTF-8 として正しくない値は残さない）
            foreach (['date', 'team', 'competition'] as $field) {
                $value = $request->query($field);
                $searchParams[$field] = is_string($value) && mb_check_encoding($value, 'UTF-8') ? $value : '';
            }
        } else {
            $searchParams['date'] = (string) $request->query('date', '');
            $searchParams['team'] = trim((string) $request->query('team', ''));
            $searchParams['competition'] = (string) $request->query('competition', '');
            $searchParams['period'] = $request->query('period') === 'past' ? 'past' : 'upcoming';
            // 検索モードかどうかは、上で決めた $isSearchInput（空白だけの値は入力なしとみなす）を使う
            $isSearching = $isSearchInput;
        }

        if ($isSearching) {
            $showPeriodTabs = $searchParams['date'] === '';
            $searchResults = $this->searchMatches($searchParams, $request->query('page'));
        }

        // 配列などの文字列でない値や UTF-8 として正しくない値は、指定なしとして既定値（PL・第1節・ステージ未指定）にする
        $competitionInput = $request->input('competition');
        $competitionCode = (is_string($competitionInput) && mb_check_encoding($competitionInput, 'UTF-8') ? $competitionInput : '') ?: 'PL';
        $matchDayInput = $request->input('match_day');
        $matchDay = is_string($matchDayInput) ? (int) $matchDayInput : 1;
        $stageInput = $request->input('stage');
        $stage = is_string($stageInput) && mb_check_encoding($stageInput, 'UTF-8') ? $stageInput : '';

        $competition = Competition::where('code', $competitionCode)->first();

        $footballMatches = collect();
        $matchDays = collect();
        $stages = collect();
        $isRegularSeasonOnly = true;
        if ($competition && ! $isSearching) {
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

                if ($stage === '') {
                    $stage = $stages->first() ?? '';
                }

                $footballMatches = FootballMatch::with(['homeTeam', 'awayTeam'])
                    ->where('competition_id', $competition->id)
                    ->where('stage', $stage)
                    ->oldest('kickoff_at')
                    ->get();
            }
        }

        $competitions = Competition::orderByRaw('COALESCE(name_ja, name)')->orderBy('id')->get();

        /** @var User $user */
        $user = Auth::user();
        // 日本語名の順（日本語名がなければ英語名）。同じ名前なら ID の順（マイページと同じ）
        $favoriteTeams = $user->favoriteTeams()->orderByRaw('COALESCE(teams.name_ja, teams.name)')->orderBy('teams.id')->get();
        $favoriteTeamIds = $favoriteTeams->pluck('id');

        // 検索結果を出すときはお気に入りのブロックを出さないので、試合の取得は省く
        $favoriteTeamMatches = $isSearching ? collect() : $favoriteTeams->map(function (Team $favoriteTeam) {
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
            'isSearching',
            'searchResults',
            'searchParams',
            'searchErrors',
            'showPeriodTabs',
        ));
    }

    /**
     * 検索条件に合う試合を、1ページ20件で返す。
     * 日付指定ありはその日の試合をキックオフの早い順に出す。日付なしのチーム検索は今後（早い順）／過去（新しい順）で分ける。
     *
     * @param  array{date: string, team: string, competition: string, period: string}  $params
     * @return LengthAwarePaginator<int, FootballMatch>
     */
    private function searchMatches(array $params, mixed $pageInput): LengthAwarePaginator
    {
        $query = FootballMatch::with(['homeTeam', 'awayTeam', 'competition']);

        if ($params['competition'] !== '') {
            $query->whereRelation('competition', 'code', $params['competition']);
        }
        if ($params['team'] !== '') {
            $query->involvingTeamLike($params['team']);
        }

        if ($params['date'] !== '') {
            $query->kickoffOnJstDate($params['date'])->orderBy('kickoff_at')->orderBy('id');
        } elseif ($params['period'] === 'past') {
            $query->where('kickoff_at', '<', now())->orderByDesc('kickoff_at')->orderByDesc('id');
        } else {
            $query->where('kickoff_at', '>=', now())->orderBy('kickoff_at')->orderBy('id');
        }

        // 巨大なページ番号だと offset の計算が int の範囲を超えて 500 になるため、上限（1万ページ）を付ける
        // 先頭の 0 は取り除いてから桁数を見る（0000000001 は 1 ページ目）
        $page = 1;
        if (is_string($pageInput) && ctype_digit($pageInput)) {
            $digits = ltrim($pageInput, '0');
            $page = strlen($digits) > 5 ? self::MAX_SEARCH_PAGE : min(max((int) $digits, 1), self::MAX_SEARCH_PAGE);
        }

        // 件数を先に1回だけ数え、最後のページより大きいページ番号は最後のページに丸める（0件のときは1ページ目）
        $total = $query->toBase()->getCountForPagination();
        $page = min($page, max((int) ceil($total / 20), 1));

        $results = $query->paginate(20, ['*'], 'page', $page, $total);

        return $results->withQueryString();
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
        $requestedSort = $request->query('sort');
        $sort = in_array($requestedSort, self::BROADCAST_SORTS, true)
            ? $requestedSort
            : self::BROADCAST_SORTS[0];
        $broadcastShops = $isFinished ? collect() : $this->broadcastShops($footballMatch, $sort, $user);

        return view('matches.show', compact('footballMatch', 'hasFavoriteTeams', 'showFavoriteOnly', 'isFinished', 'broadcastShops', 'sort'));
    }

    /**
     * 試合を放映する店舗の一覧（1店舗＝1行）を並び順どおりに返す。
     * 放映・店舗・座席種別・予約の合計をまとめて取得し、店舗数が増えてもクエリ数が増えないようにする。
     *
     * @return Collection<int, array{shop: Shop, minPrice: int, seats: int, reservationStatus: string|null, broadcastId: int, canReserve: bool, reservationId: int|null}>
     */
    private function broadcastShops(FootballMatch $footballMatch, string $sort, User $user): Collection
    {
        // ログインユーザーのこの試合の予約（confirmed、1件まで）を、放映の ID とあわせて1回のクエリで取得する
        // 「この試合の confirmed の予約」の条件は、ReservationService::hasReservedMatch() と同じスコープで決める
        $reservation = Reservation::query()
            ->confirmed()
            ->where('reservations.user_id', $user->id)
            ->forFootballMatch($footballMatch->id)
            ->join('broadcast_seat_types', 'broadcast_seat_types.id', '=', 'reservations.broadcast_seat_type_id')
            ->orderBy('reservations.id')
            ->first(['reservations.id', 'broadcast_seat_types.broadcast_id']);
        $broadcasts = Broadcast::query()
            ->where('football_match_id', $footballMatch->id)
            ->published()
            ->whereRelation('shop', 'status', Shop::STATUS_PUBLISHED)
            ->with([
                'shop',
                'activeBroadcastSeatTypes' => fn ($query) => $query->withSum('occupyingReservations as occupied_people', 'number_of_people'),
            ])
            ->get();

        $rows = collect();
        foreach ($broadcasts as $broadcast) {
            $shop = $broadcast->shop;
            // 有効な座席種別がない放映は出さない（店舗は whereRelation で公開中のものに絞り込み済み）
            if ($shop === null || $broadcast->activeBroadcastSeatTypes->isEmpty()) {
                continue;
            }

            // 定員を超えた予約データがあっても、残席はマイナスにしない
            $seats = $broadcast->activeBroadcastSeatTypes->sum(
                fn (BroadcastSeatType $seatType) => $seatType->remainingSeats()
            );
            // 試合は取得済みのものを使い回す（放映ごとに読み込み直さない）
            $broadcast->setRelation('footballMatch', $footballMatch);
            $canReserve = $broadcast->isReservable();

            $rows->push([
                'shop' => $shop,
                'minPrice' => (int) $broadcast->activeBroadcastSeatTypes->min('price'),
                'seats' => $seats,
                'reservationStatus' => $broadcast->reservationStatus(),
                'broadcastId' => $broadcast->id,
                // この試合を予約済みなら、どの店舗にも「予約する」を出さない
                'canReserve' => $reservation === null && $canReserve && $seats > 0,
                'reservationId' => $reservation !== null && (int) $reservation->getAttribute('broadcast_id') === $broadcast->id ? (int) $reservation->id : null,
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
}
