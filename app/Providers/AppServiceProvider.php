<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 店舗オーナー向けの画面（/owner/...）に入れるのは shop_owner だけ。admin は対象外
        Gate::define('access-owner-area', fn (User $user): bool => $user->role === 'shop_owner');

        // URL の ID は数字だけ（数字以外は 404）。全ルートのパラメータをここでまとめて限る
        foreach (['shop', 'broadcast', 'reservation', 'seatType', 'id'] as $parameter) {
            Route::pattern($parameter, '[0-9]+');
        }

        // ユーザー登録：IP ごとに1分に5回まで（成功・失敗を問わず送信の回数）
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // 店舗の登録：ユーザーごとに1分に10回まで
        RateLimiter::for('owner-shop-store', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
