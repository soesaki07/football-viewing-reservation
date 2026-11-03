<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ShopPolicy
{
    /**
     * 店舗の登録は shop_owner だけができる（それ以外は 403）。
     */
    public function create(User $user): Response
    {
        return $user->role === 'shop_owner'
            ? Response::allow()
            : Response::deny();
    }

    /**
     * 店舗の予約一覧は、その店舗のオーナーだけが見られる。他のオーナーの店舗は存在を隠すため 404 にする。
     * user_id は DB ドライバによって文字列で返ることがあるため、型をそろえて比べる。
     */
    public function viewReservations(User $user, Shop $shop): Response
    {
        return (int) $user->id === (int) $shop->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * 店舗の放映の管理（一覧・登録）は、その店舗のオーナー（shop_owner）だけができる。
     * shop_owner 以外は 403、他のオーナーの店舗は存在を隠すため 404 にする。
     */
    public function manageBroadcasts(User $user, Shop $shop): Response
    {
        if ($user->role !== 'shop_owner') {
            return Response::deny();
        }

        return (int) $user->id === (int) $shop->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * 店舗の座席種別の管理（一覧・登録）は、その店舗のオーナー（shop_owner）だけができる。
     * shop_owner 以外は 403、他のオーナーの店舗は存在を隠すため 404 にする。
     */
    public function manageSeatTypes(User $user, Shop $shop): Response
    {
        if ($user->role !== 'shop_owner') {
            return Response::deny();
        }

        return (int) $user->id === (int) $shop->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * 店舗情報の確認・編集は、その店舗のオーナー（shop_owner）だけができる。
     * shop_owner 以外は 403、他のオーナーの店舗は存在を隠すため 404 にする。
     * user_id は DB ドライバによって文字列で返ることがあるため、型をそろえて比べる。
     */
    public function update(User $user, Shop $shop): Response
    {
        if ($user->role !== 'shop_owner') {
            return Response::deny();
        }

        return (int) $user->id === (int) $shop->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
