<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\SeatTypeRequest;
use App\Http\Requests\StoreSeatTypeRequest;
use App\Http\Requests\UpdateSeatTypeActiveRequest;
use App\Http\Requests\UpdateSeatTypeRequest;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SeatTypeController extends Controller
{
    /**
     * 店舗オーナーの座席種別一覧。選択中の店舗の座席種別を、有効なものが先、その中は ID 順で表示する。
     */
    public function index(Request $request): View
    {
        [$shops, $shop] = $this->resolveShop($request);
        $headerData = $this->headerData($request);

        if ($shop === null) {
            return view('owner.seat-types.index', compact('shops') + $headerData);
        }

        $seatTypes = SeatType::query()
            // 必ず認可済みの店舗の座席種別に限定する
            ->where('shop_id', $shop->id)
            // 使っている放映の数をまとめて数える（N+1 を避ける）
            ->withCount('broadcastSeatTypes')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        return view('owner.seat-types.index', compact('shops', 'shop', 'seatTypes') + $headerData);
    }

    /**
     * 座席種別の登録画面。
     */
    public function create(Request $request): View
    {
        [$shops, $shop] = $this->resolveShop($request);
        $headerData = $this->headerData($request);

        return view('owner.seat-types.create', compact('shops', 'shop') + $headerData);
    }

    /**
     * 座席種別を登録する。入力の検証・認可は StoreSeatTypeRequest で行う。
     */
    public function store(StoreSeatTypeRequest $request): RedirectResponse
    {
        $shop = $request->shop();

        try {
            // 店舗はサーバー側で決めた値を使う（リクエストの値をそのまま渡さない）
            $shop->seatTypes()->create($request->seatTypeAttributes() + ['is_active' => true]);
        } catch (UniqueConstraintViolationException) {
            // 同時に同じ名前を登録されたとき。500 にせず入力画面へ戻す
            return redirect()
                ->route('owner.seat-types.create', ['shop' => $shop->id])
                ->withInput()
                ->withErrors(['name' => SeatTypeRequest::DUPLICATE_NAME_MESSAGE]);
        }

        return redirect()
            ->route('owner.seat-types.index', ['shop' => $shop->id])
            ->with('success', '座席種別を登録しました。');
    }

    /**
     * 座席種別の編集画面。
     */
    public function edit(Request $request, SeatType $seatType): View
    {
        // 他店・削除済みの店舗の座席種別は 404、shop_owner 以外は 403
        Gate::authorize('update', $seatType);

        $seatType->loadCount('broadcastSeatTypes');
        $shop = $seatType->shop;

        return view('owner.seat-types.edit', compact('shop', 'seatType') + $this->headerData($request));
    }

    /**
     * 座席種別を更新する。入力の検証・認可は UpdateSeatTypeRequest で行う。
     * 既定の席数・料金は新しい放映の初期値なので、登録済みの放映の席数・料金は変わらない。
     */
    public function update(UpdateSeatTypeRequest $request, SeatType $seatType): RedirectResponse
    {
        try {
            $seatType->update($request->seatTypeAttributes());
        } catch (UniqueConstraintViolationException) {
            // 同時に同じ名前に変えられたとき。500 にせず入力画面へ戻す
            return redirect()
                ->route('owner.seat-types.edit', $seatType)
                ->withInput()
                ->withErrors(['name' => SeatTypeRequest::DUPLICATE_NAME_MESSAGE]);
        }

        return redirect()
            ->route('owner.seat-types.index', ['shop' => $seatType->shop_id])
            ->with('success', '座席種別を更新しました。');
    }

    /**
     * 座席種別を有効・無効にする。無効にしても、登録済みの予約はそのまま残る。
     */
    public function updateActive(UpdateSeatTypeActiveRequest $request, SeatType $seatType): RedirectResponse
    {
        $isActive = $request->boolean('is_active');
        $seatType->update(['is_active' => $isActive]);

        return redirect()
            ->route('owner.seat-types.index', ['shop' => $seatType->shop_id])
            ->with('success', $isActive ? '座席種別を有効にしました。' : '座席種別を無効にしました。');
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
        Gate::authorize('manageSeatTypes', $shop);

        return [$shops, $shop];
    }
}
