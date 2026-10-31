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
}
