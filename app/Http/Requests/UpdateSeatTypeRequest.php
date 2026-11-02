<?php

namespace App\Http\Requests;

use App\Models\SeatType;
use Illuminate\Support\Facades\Gate;

class UpdateSeatTypeRequest extends SeatTypeRequest
{
    /**
     * 自分の店舗の座席種別だけ編集できる。他店・削除済みの店舗の座席種別は 404、shop_owner 以外は 403。
     * 入力の検証より先に認可する（他店の存在を隠すため）。
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->seatType());

        return true;
    }

    /**
     * 編集する座席種別（ルートモデルバインディング）。店舗は変えられないので、リクエストの shop_id は使わない。
     */
    public function seatType(): SeatType
    {
        /** @var SeatType */
        return $this->route('seatType');
    }

    protected function shopId(): int
    {
        return $this->seatType()->shop_id;
    }

    protected function ignoreSeatTypeId(): ?int
    {
        return $this->seatType()->id;
    }
}
