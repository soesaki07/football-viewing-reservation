<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShopController extends Controller
{
    // 店舗詳細に表示する放映予定試合の最大件数
    private const UPCOMING_MATCHES_LIMIT = 10;

    public function show(Request $request, Shop $shop): View
    {
        // 非公開の店舗は存在しないものとして扱う（削除済みはルートモデルバインディングで 404 になる）
        abort_unless($shop->isPublished(), 404);

        $seatTypes = $shop->seatTypes()
            ->where('is_active', true)
            ->orderBy('default_price')
            ->orderBy('id')
            ->get();

        // この店舗の公開中の放映がある、これからの試合をキックオフの早い順に取得する
        $upcomingMatches = FootballMatch::with(['competition', 'homeTeam', 'awayTeam'])
            ->whereHas('broadcasts', function (Builder $query) use ($shop) {
                $query->where('shop_id', $shop->id)->published();
            })
            ->where('kickoff_at', '>', now())
            ->orderBy('kickoff_at')
            ->limit(self::UPCOMING_MATCHES_LIMIT)
            ->get();

        $mapsEmbedKey = config('services.google_maps.embed_key');

        /** @var User $user */
        $user = Auth::user();
        $hasFavoriteTeams = $user->favoriteTeams->isNotEmpty();
        $showFavoriteOnly = $hasFavoriteTeams && $request->boolean('favorite', true);

        return view('shops.show', compact('shop', 'seatTypes', 'upcomingMatches', 'mapsEmbedKey', 'hasFavoriteTeams', 'showFavoriteOnly'));
    }
}
