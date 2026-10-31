<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class ReservationRequest extends FormRequest
{
    /**
     * 予約できない放映（非公開・受付期間外・試合終了など）への入力は受け付けない。
     */
    public function authorize(): bool
    {
        return $this->broadcast()->isReservable();
    }

    /**
     * 予約できない放映のときは、403 ではなく試合詳細に戻してメッセージを表示する。
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('matches.show', $this->broadcast()->football_match_id)
                ->with('error', 'この放映は現在予約できません。')
        );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'broadcast_seat_type_id' => [
                'required',
                'integer',
                // この放映の、予約できる（有効な）座席種別だけを許可する
                function (string $attribute, mixed $value, Closure $fail) {
                    $exists = $this->broadcast()->activeBroadcastSeatTypes()
                        ->where('broadcast_seat_types.id', $value)
                        ->exists();
                    if (! $exists) {
                        $fail('選択された座席種別は予約できません。');
                    }
                },
            ],
            'number_of_people' => ['required', 'integer', 'min:1'],
            'customer_note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * 人数の上限（1予約あたりの上限・残席）は、座席種別が決まってから確認する。
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $seatType = $this->broadcast()->activeBroadcastSeatTypes()
                    ->where('broadcast_seat_types.id', $this->integer('broadcast_seat_type_id'))
                    ->first();
                $numberOfPeople = $this->integer('number_of_people');

                if ($seatType === null) {
                    return;
                }
                if ($numberOfPeople > $seatType->max_people_per_reservation) {
                    $validator->errors()->add('number_of_people', "1回の予約は{$seatType->max_people_per_reservation}人までです。");
                } elseif ($numberOfPeople > $seatType->remainingSeats()) {
                    $validator->errors()->add('number_of_people', "残席は{$seatType->remainingSeats()}席です。");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'broadcast_seat_type_id' => '座席種別',
            'number_of_people' => '人数',
            'customer_note' => '備考',
        ];
    }

    private function broadcast(): Broadcast
    {
        /** @var Broadcast */
        return $this->route('broadcast');
    }
}
