<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ShopPolicy
{
    /**
     * 店舗の予約一覧は、その店舗のオーナーだけが見られる。他のオーナーの店舗は存在を隠すため 404 にする。
     */
    public function viewReservations(User $user, Shop $shop): Response
    {
        return $user->id === $shop->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
