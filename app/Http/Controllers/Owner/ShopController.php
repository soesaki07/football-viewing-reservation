<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateShopRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopController extends Controller
{
    /**
     * 店舗オーナーの店舗情報の一覧。自分の店舗を ID 順に表示する（削除済みは出ない。非公開も含む）。
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $shops = Shop::query()->where('user_id', $user->id)->orderBy('id')->get();

        return view('owner.shops.index', compact('shops') + $this->headerData($request));
    }

    /**
     * 店舗情報の編集画面。
     */
    public function edit(Request $request, Shop $shop): View
    {
        // 他店は 404、shop_owner 以外は 403（削除済みの店舗はルートモデルバインディングで 404）
        Gate::authorize('update', $shop);

        return view('owner.shops.edit', compact('shop') + $this->headerData($request));
    }

    /**
     * 店舗情報を更新する。入力の検証・認可は UpdateShopRequest で行う。
     * 同時に編集されたときは、後から保存したものが残る。
     */
    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $attributes = $request->shopAttributes();

        // 住所が変わったら、古い位置情報を消す（地図は住所から出す。位置は Google Maps の機能で入れ直す）
        // 元の郵便番号もそろえた形で比べる（ハイフンなしで保存済みでも、住所を変えていなければ消さない）
        $addressChanged = Shop::normalizePostalCode($shop->postal_code) !== $attributes['postal_code']
            || $shop->prefecture !== $attributes['prefecture']
            || $shop->city !== $attributes['city']
            || $shop->address_line !== $attributes['address_line'];

        $shop->fill($attributes);

        if ($addressChanged) {
            $shop->latitude = null;
            $shop->longitude = null;
            $shop->google_place_id = null;
        }

        $shop->save();

        return redirect()
            ->route('owner.shops.index')
            ->with('success', '店舗情報を更新しました。');
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
}
