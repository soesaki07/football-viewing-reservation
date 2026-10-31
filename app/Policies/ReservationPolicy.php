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
}
