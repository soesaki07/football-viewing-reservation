<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
    }
}
