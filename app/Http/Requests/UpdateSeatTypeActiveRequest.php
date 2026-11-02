<?php

namespace App\Http\Requests;

use App\Models\SeatType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSeatTypeActiveRequest extends FormRequest
{
    /**
     * 自分の店舗の座席種別だけ切り替えられる。他店・削除済みの店舗の座席種別は 404、shop_owner 以外は 403。
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->seatType());

        return true;
    }

    public function seatType(): SeatType
    {
        /** @var SeatType */
        return $this->route('seatType');
    }

    /**
     * 切り替えではなく、なりたい状態を明示して送る（二重送信しても結果が変わらない）。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.required' => '有効・無効の指定が正しくありません。',
            'is_active.boolean' => '有効・無効の指定が正しくありません。',
        ];
    }
}
