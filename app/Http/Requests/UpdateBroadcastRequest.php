<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Models\FootballMatch;
use App\Models\SeatType;
use App\Services\BroadcastService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class UpdateBroadcastRequest extends BroadcastRequest
{
    /**
     * 自分の店舗の放映だけ編集できる。他店・削除済みの店舗の放映は 404、shop_owner 以外は 403。
     * 入力の検証より先に認可する（他店の存在を隠すため）。
     * 終了した試合の放映は、一覧に戻してメッセージを出す（検証エラーより先に伝える）。
     */
    public function authorize(): bool
    {
        $broadcast = $this->broadcast();
        Gate::authorize('update', $broadcast);
        $broadcast->loadMissing('footballMatch');

        if ($broadcast->footballMatch->isFinished()) {
            throw new HttpResponseException(
                redirect()
                    ->route('owner.broadcasts.index', ['shop' => $broadcast->shop_id])
                    ->with('error', BroadcastService::FINISHED_MESSAGE)
            );
        }

        return true;
    }

    /**
     * 試合・店舗は変えられないので、リクエストの shop_id・football_match_id は使わない。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->commonRules();
    }

    /**
     * 編集する放映（ルートモデルバインディング）。
     */
    public function broadcast(): Broadcast
    {
        /** @var Broadcast */
        return $this->route('broadcast');
    }

    /**
     * 選択できるのは、その店舗の有効な座席種別か、この放映で今販売中の座席種別。
     * 無効な座席種別を販売中に戻すことはできない。
     */
    protected function seatTypeIdsAreSelectable(array $ids): bool
    {
        $broadcast = $this->broadcast();

        $count = SeatType::query()
            ->where('shop_id', $broadcast->shop_id)
            ->whereIn('id', $ids)
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', $broadcast->broadcastSeatTypes()->where('is_active', true)->select('seat_type_id')))
            ->count();

        return $count === count($ids);
    }

    protected function targetMatch(): ?FootballMatch
    {
        return $this->broadcast()->footballMatch;
    }
}
