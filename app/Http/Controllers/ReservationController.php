<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationException;
use App\Http\Requests\ReservationRequest;
use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReservationController extends Controller
{
    /**
     * 予約入力画面。確認画面から戻ってきたときは、セッションの入力値を初期値にする。
     */
    public function create(Request $request, Broadcast $broadcast): View|RedirectResponse
    {
        if (! $broadcast->isReservable()) {
            return $this->backToMatch($broadcast, $request->session()->get('error', 'この放映は現在予約できません。'));
        }

        $broadcast->load(['footballMatch.homeTeam', 'footballMatch.awayTeam', 'shop']);
        $seatTypes = $this->seatTypes($broadcast);
        $input = $request->session()->get($this->sessionKey($broadcast), []);

        return view('reservations.create', [...compact('broadcast', 'seatTypes', 'input'), ...$this->navigation($request)]);
    }

    /**
     * 予約確認画面。入力値を検証してセッションに保存し、内容を表示する。
     */
    public function confirm(ReservationRequest $request, Broadcast $broadcast): View
    {
        $input = $request->safe()->only(['broadcast_seat_type_id', 'number_of_people', 'customer_note']);
        $request->session()->put($this->sessionKey($broadcast), $input);

        $broadcast->load(['footballMatch.homeTeam', 'footballMatch.awayTeam', 'shop']);
        $seatType = $broadcast->activeBroadcastSeatTypes()
            ->with('seatType')
            ->where('broadcast_seat_types.id', (int) $input['broadcast_seat_type_id'])
            ->firstOrFail();
        $numberOfPeople = (int) $input['number_of_people'];
        // 表示用の合計金額（確定時はサーバー側で改めて計算する）
        $totalPrice = $seatType->price * $numberOfPeople;
        $customerNote = $input['customer_note'] ?? null;

        return view('reservations.confirm', [...compact('broadcast', 'seatType', 'numberOfPeople', 'totalPrice', 'customerNote'), ...$this->navigation($request)]);
    }

    /**
     * 予約確定。入力値はセッションから取り出し、リクエストの値は使わない。
     */
    public function store(Request $request, Broadcast $broadcast, ReservationService $reservationService): RedirectResponse
    {
        $key = $this->sessionKey($broadcast);
        $input = $request->session()->get($key);
        if (! is_array($input) || ! isset($input['broadcast_seat_type_id'], $input['number_of_people'])) {
            return redirect()->route('reservations.create', $broadcast)
                ->with('error', '入力内容が見つかりません。もう一度入力してください。');
        }

        /** @var User $user */
        $user = Auth::user();

        try {
            $reservation = $reservationService->reserve(
                $user,
                $broadcast,
                (int) $input['broadcast_seat_type_id'],
                (int) $input['number_of_people'],
                $input['customer_note'] ?? null,
            );
        } catch (ReservationException $e) {
            return redirect()->route('reservations.create', $broadcast)->with('error', $e->getMessage());
        }

        $request->session()->forget($key);

        return redirect()->route('reservations.complete', $reservation);
    }

    /**
     * 予約完了画面。予約した本人だけが見られる。
     */
    public function complete(Request $request, Reservation $reservation): View
    {
        Gate::authorize('view', $reservation);

        // 店舗が後から削除されても完了画面は表示できるよう、削除済みも読み込む
        $reservation->load([
            'broadcastSeatType.seatType',
            'broadcastSeatType.broadcast.footballMatch.homeTeam',
            'broadcastSeatType.broadcast.footballMatch.awayTeam',
        ]);

        $broadcast = $reservation->broadcastSeatType->broadcast;
        $shop = Shop::withTrashed()->findOrFail($broadcast->shop_id);

        return view('reservations.complete', [...compact('reservation', 'broadcast', 'shop'), ...$this->navigation($request)]);
    }

    /**
     * レイアウトのナビゲーションが使う、お気に入りチームの有無。
     *
     * @return array{hasFavoriteTeams: bool, showFavoriteOnly: bool}
     */
    private function navigation(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();
        $hasFavoriteTeams = $user->favoriteTeams->isNotEmpty();

        return [
            'hasFavoriteTeams' => $hasFavoriteTeams,
            'showFavoriteOnly' => $hasFavoriteTeams && $request->boolean('favorite', true),
        ];
    }

    private function backToMatch(Broadcast $broadcast, string $message): RedirectResponse
    {
        return redirect()->route('matches.show', $broadcast->football_match_id)->with('error', $message);
    }

    private function sessionKey(Broadcast $broadcast): string
    {
        return 'reservation_input.'.$broadcast->id;
    }

    /**
     * 予約できる座席種別を、残席の合計つきで取得する。
     *
     * @return Collection<int, BroadcastSeatType>
     */
    private function seatTypes(Broadcast $broadcast)
    {
        return $broadcast->activeBroadcastSeatTypes()
            ->with('seatType')
            ->withSum('confirmedReservations as confirmed_people', 'number_of_people')
            ->orderBy('price')
            ->orderBy('id')
            ->get();
    }
}
