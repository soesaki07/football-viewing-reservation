<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Models\FootballMatch;
use App\Models\SeatType;
use App\Models\Shop;
use App\Services\BroadcastService;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBroadcastRequest extends FormRequest
{
    // datetime-local の入力形式（JST）
    public const DATETIME_FORMAT = 'Y-m-d\TH:i';

    public const MAX_PRICE = 1000000;

    public const MAX_CAPACITY = 65535;

    // reservations.total_price（unsignedInteger）の上限
    public const MAX_TOTAL_PRICE = 4294967295;

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
     * 販売する座席（チェックを付けたもの）だけを検証の対象にする。
     */
    protected function prepareForValidation(): void
    {
        $seats = $this->input('seats');
        if (! is_array($seats)) {
            return;
        }

        $this->merge([
            'seats' => array_filter($seats, fn ($seat) => is_array($seat) && ! empty($seat['selected'])),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shop = $this->shop();

        return [
            'football_match_id' => [
                'required',
                'integer',
                // キックオフ前で、終了とみなされる status ではない試合に限る
                Rule::exists('football_matches', 'id')->where(fn ($query) => $query
                    ->where('kickoff_at', '>', now()->utc())
                    ->whereNotIn('status', FootballMatch::FINISHED_STATUSES)),
                // その店舗で未登録の試合に限る
                Rule::unique('broadcasts', 'football_match_id')->where('shop_id', $shop->id),
            ],
            'reservation_start_at' => ['required', 'date_format:'.self::DATETIME_FORMAT],
            'reservation_end_at' => ['required', 'date_format:'.self::DATETIME_FORMAT],
            'status' => ['required', Rule::in(['draft', Broadcast::STATUS_PUBLISHED])],
            'seats' => [
                'required',
                'array',
                'min:1',
                // その店舗の有効な座席種別に限る（他店・無効な座席種別の ID は拒否）
                function (string $attribute, mixed $value, Closure $fail) use ($shop) {
                    // 配列でないときは array ルールのエラーに任せる（ここで array_keys などを呼ばない）
                    if (! is_array($value)) {
                        return;
                    }

                    $ids = array_keys($value);
                    foreach ($ids as $id) {
                        if (! ctype_digit((string) $id)) {
                            $fail('選択された座席種別は登録できません。');

                            return;
                        }
                    }

                    $count = SeatType::query()
                        ->where('shop_id', $shop->id)
                        ->where('is_active', true)
                        ->whereIn('id', $ids)
                        ->count();
                    if ($count !== count($ids)) {
                        $fail('選択された座席種別は登録できません。');
                    }
                },
            ],
            'seats.*.capacity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CAPACITY],
            'seats.*.price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'seats.*.max_people_per_reservation' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CAPACITY],
        ];
    }

    /**
     * 受付期間の前後関係と、最大人数が販売席数以下かを確認する。
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $errors = $validator->errors();

                // 受付期間：日時と試合の入力が正しいときだけ確認する
                if (! $errors->hasAny(['reservation_start_at', 'reservation_end_at', 'football_match_id'])) {
                    $start = $this->parseJst((string) $this->input('reservation_start_at'));
                    $end = $this->parseJst((string) $this->input('reservation_end_at'));
                    $match = FootballMatch::query()->find($this->integer('football_match_id'));

                    if ($start !== null && $end !== null && $match !== null) {
                        if ($start->gte($end)) {
                            $errors->add('reservation_end_at', '予約終了日時は、予約開始日時より後にしてください。');
                        } elseif ($end->gt($match->kickoff_at->copy()->addHours(3))) {
                            // 試合終了（キックオフの3時間後）より後には受け付けない
                            $errors->add('reservation_end_at', '予約終了日時は、試合の終了（キックオフの3時間後）以前にしてください。');
                        }
                    }
                }

                // 最大人数：その座席の席数と最大人数の入力が正しいときだけ確認する
                $seats = $this->input('seats');
                if (! is_array($seats)) {
                    return;
                }
                foreach ($seats as $id => $seat) {
                    if (! is_array($seat) || $errors->hasAny(["seats.{$id}.capacity", "seats.{$id}.max_people_per_reservation"])) {
                        continue;
                    }
                    if (! isset($seat['capacity'], $seat['max_people_per_reservation'])) {
                        continue;
                    }
                    if ((int) $seat['max_people_per_reservation'] > (int) $seat['capacity']) {
                        $errors->add("seats.{$id}.max_people_per_reservation", '1予約の最大人数は、販売席数以下にしてください。');
                    } elseif (! $errors->has("seats.{$id}.price") && isset($seat['price'])
                        && (int) $seat['price'] * (int) $seat['max_people_per_reservation'] > self::MAX_TOTAL_PRICE) {
                        // 予約の合計金額（料金 × 人数）が reservations.total_price に入りきらなくなるのを防ぐ
                        $errors->add("seats.{$id}.max_people_per_reservation", '料金との組み合わせで予約の合計金額が上限を超えるため、1予約の最大人数を減らしてください。');
                    }
                }
            },
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
            'reservation_start_at.required' => '予約開始日時を入力してください。',
            'reservation_start_at.date_format' => '予約開始日時の形式が正しくありません。',
            'reservation_end_at.required' => '予約終了日時を入力してください。',
            'reservation_end_at.date_format' => '予約終了日時の形式が正しくありません。',
            'status.required' => '放映ステータスを選択してください。',
            'status.in' => '放映ステータスが正しくありません。',
            'seats.required' => '販売する座席を1つ以上選択してください。',
            'seats.array' => '販売する座席を1つ以上選択してください。',
            'seats.min' => '販売する座席を1つ以上選択してください。',
            'seats.*.capacity.required' => '販売席数を入力してください。',
            'seats.*.capacity.integer' => '販売席数は整数で入力してください。',
            'seats.*.capacity.min' => '販売席数は1以上で入力してください。',
            'seats.*.capacity.max' => '販売席数は'.self::MAX_CAPACITY.'以下で入力してください。',
            'seats.*.price.required' => '料金を入力してください。',
            'seats.*.price.integer' => '料金は整数で入力してください。',
            'seats.*.price.min' => '料金は0以上で入力してください。',
            'seats.*.price.max' => '料金は'.number_format(self::MAX_PRICE).'以下で入力してください。',
            'seats.*.max_people_per_reservation.required' => '1予約の最大人数を入力してください。',
            'seats.*.max_people_per_reservation.integer' => '1予約の最大人数は整数で入力してください。',
            'seats.*.max_people_per_reservation.min' => '1予約の最大人数は1以上で入力してください。',
            'seats.*.max_people_per_reservation.max' => '1予約の最大人数は'.self::MAX_CAPACITY.'以下で入力してください。',
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
     * 受付期間（JST で入力された値）を UTC の Carbon にする。形式が不正なら null。
     */
    public function parseJst(string $value): ?Carbon
    {
        try {
            return Carbon::createFromFormat(self::DATETIME_FORMAT, $value, 'Asia/Tokyo')->utc();
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * 検証済みの座席を、保存用の形（座席種別 ID 付きの配列）にする。
     *
     * @return list<array{seat_type_id: int, capacity: int, price: int, max_people_per_reservation: int}>
     */
    public function seatsForStore(): array
    {
        $seats = [];
        foreach ($this->validated('seats') as $id => $seat) {
            $seats[] = [
                'seat_type_id' => (int) $id,
                'capacity' => (int) $seat['capacity'],
                'price' => (int) $seat['price'],
                'max_people_per_reservation' => (int) $seat['max_people_per_reservation'],
            ];
        }

        return $seats;
    }
}
