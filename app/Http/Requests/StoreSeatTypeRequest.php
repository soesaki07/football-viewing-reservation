<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StoreSeatTypeRequest extends SeatTypeRequest
{
    private ?Shop $shop = null;

    /**
     * 自分の店舗の座席種別だけ登録できる。存在しない・削除済み・他のオーナーの店舗は 404、shop_owner 以外は 403。
     * 入力の検証より先に認可する（他店の存在を隠すため）。
     */
    public function authorize(): bool
    {
        Gate::authorize('manageSeatTypes', $this->shop());

        return true;
    }

    /**
     * 登録先の店舗。ログイン中のオーナーの店舗の中から探し、見つからなければ 404。
     */
    public function shop(): Shop
    {
        if ($this->shop === null) {
            // 配列などスカラーでない値は、存在しない店舗と同じく 404
            abort_unless(is_scalar($this->input('shop_id')), 404);

            $this->shop = Shop::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->integer('shop_id'));
        }

        return $this->shop;
    }

    protected function shopId(): int
    {
        return $this->shop()->id;
    }
}
