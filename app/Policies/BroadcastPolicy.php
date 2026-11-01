<?php

namespace App\Policies;

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BroadcastPolicy
{
    /**
     * 放映の編集は、その放映の店舗のオーナー（shop_owner）だけができる。
     * shop_owner 以外は 403、他のオーナーの店舗・削除済みの店舗の放映は存在を隠すため 404 にする。
     * user_id は DB ドライバによって文字列で返ることがあるため、型をそろえて比べる。
     */
    public function update(User $user, Broadcast $broadcast): Response
    {
        if ($user->role !== 'shop_owner') {
            return Response::deny();
        }

        // 削除済みの店舗は SoftDeletes により null になる
        $shop = $broadcast->shop;

        return $shop !== null && (int) $user->id === (int) $shop->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
