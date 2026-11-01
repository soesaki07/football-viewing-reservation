<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReservationPolicy
{
    /**
     * 予約は、予約した本人だけが見られる。他人の予約は存在を隠すため 404 にする。
     */
    public function view(User $user, Reservation $reservation): Response
    {
        return $user->id === $reservation->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * 予約のキャンセルは、予約した本人だけができる。閲覧（view）とは別にして、閲覧できる人を広げてもキャンセルできる人が増えないようにする。
     * 他人の予約は存在を隠すため 404 にする。キャンセルできる状態か（confirmed・キックオフ前）はここでは見ない（モデル・サービスで判定する）。
     */
    public function cancel(User $user, Reservation $reservation): Response
    {
        return $user->id === $reservation->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * 来店状態（来店済み・来店なし）の更新は、その予約の放映の店舗のオーナー（shop_owner）だけができる。
     * Gate（access-owner-area）に加えてここでもロールを確かめる。他店の予約・削除済みの店舗の予約は存在を隠すため 404 にする。
     * 更新できる状態か（時期・遷移）はここでは見ない（モデル・サービスで判定する）。
     */
    public function updateVisitStatus(User $user, Reservation $reservation): Response
    {
        if ($user->role !== 'shop_owner') {
            return Response::deny();
        }

        // shop は SoftDeletes のため、削除済みの店舗は null になり 404 になる（一覧にも出ない）
        $shop = $reservation->broadcastSeatType->broadcast->shop;

        return $shop !== null && $shop->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
