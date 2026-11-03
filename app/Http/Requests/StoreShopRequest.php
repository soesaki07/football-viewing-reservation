<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Support\Facades\Gate;

class StoreShopRequest extends ShopRequest
{
    /**
     * 店舗を登録できるのは shop_owner だけ（それ以外は 403）。
     * 入力の検証より先に認可する。
     */
    public function authorize(): bool
    {
        Gate::authorize('create', Shop::class);

        return true;
    }
}
