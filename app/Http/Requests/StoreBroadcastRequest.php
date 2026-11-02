<?php

namespace App\Http\Requests;

use App\Models\FootballMatch;
use App\Models\SeatType;
use App\Models\Shop;
use App\Services\BroadcastService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreBroadcastRequest extends BroadcastRequest
{
    private ?Shop $shop = null;

    /**
     * 自分の店舗の放映だけ登録できる。存在しない・削除済み・他のオーナーの店舗は 404、shop_owner 以外は 403。
     * 入力の検証より先に認可する（他店の存在を隠すため）。
     */
    public function authorize(): bool
    {
        Gate::authorize('manageBroadcasts', $this->shop());

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'football_match_id' => [
                'required',
                'integer',
                // キックオフ前で、終了とみなされる status ではない試合に限る
                Rule::exists('football_matches', 'id')->where(fn ($query) => $query
                    ->where('kickoff_at', '>', now()->utc())
                    ->whereNotIn('status', FootballMatch::FINISHED_STATUSES)),
                // その店舗で未登録の試合に限る
                Rule::unique('broadcasts', 'football_match_id')->where('shop_id', $this->shop()->id),
            ],
            ...$this->commonRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'football_match_id.required' => '試合を選択してください。',
            'football_match_id.integer' => '試合を選択してください。',
            'football_match_id.exists' => '選択された試合は登録できません。',
            'football_match_id.unique' => BroadcastService::ALREADY_REGISTERED_MESSAGE,
            ...parent::messages(),
        ];
    }

    /**
     * 登録先の店舗。ログイン中のオーナーの店舗の中から探し、見つからなければ 404。
     */
    public function shop(): Shop
    {
        if ($this->shop === null) {
            // 配列などスカラーでない値は、存在しない店舗と同じく 404
            abort_unless(is_scalar($this->input('shop_id')), 404);

            $this->shop = Shop::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->integer('shop_id'));
        }

        return $this->shop;
    }

    /**
     * その店舗の有効な座席種別に限る（他店・無効な座席種別の ID は拒否）。
     */
    protected function seatTypeIdsAreSelectable(array $ids): bool
    {
        $count = SeatType::query()
            ->where('shop_id', $this->shop()->id)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->count();

        return $count === count($ids);
    }

    protected function targetMatch(): ?FootballMatch
    {
        return FootballMatch::query()->find($this->integer('football_match_id'));
    }
}
