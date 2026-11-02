<?php

namespace App\Policies;

use App\Models\SeatType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SeatTypePolicy
{
    /**
     * 座席種別の編集・有効無効の切り替えは、その座席種別の店舗のオーナー（shop_owner）だけができる。
     * shop_owner 以外は 403、他のオーナーの店舗・削除済みの店舗の座席種別は存在を隠すため 404 にする。
     * user_id は DB ドライバによって文字列で返ることがあるため、型をそろえて比べる。
     */
    public function update(User $user, SeatType $seatType): Response
    {
        if ($user->role !== 'shop_owner') {
            return Response::deny();
        }

        // 削除済みの店舗は SoftDeletes により null になる
        $shop = $seatType->shop;

        return $shop !== null && (int) $user->id === (int) $shop->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
