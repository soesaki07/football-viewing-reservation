<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 座席種別の登録・編集で共通の入力チェック。店舗の特定と認可は継承先で決める。
 */
abstract class SeatTypeRequest extends FormRequest
{
    public const MAX_NAME_LENGTH = 100;

    public const MAX_DESCRIPTION_LENGTH = 500;

    // seat_types.default_capacity（unsignedSmallInteger）の上限
    public const MAX_CAPACITY = BroadcastRequest::MAX_CAPACITY;

    // 放映の料金と同じ上限
    public const MAX_PRICE = BroadcastRequest::MAX_PRICE;

    public const DUPLICATE_NAME_MESSAGE = 'この名前の座席種別はすでにあります。';

    /**
     * 名前の重複チェックに使う、座席種別の店舗 ID。
     */
    abstract protected function shopId(): int;

    /**
     * 名前の重複チェックで除く座席種別 ID（編集のときだけ）。
     */
    protected function ignoreSeatTypeId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 配列などの型の崩れた入力は、bail で後続のルール（DB への問い合わせ）に進ませない
            'name' => [
                'bail',
                'required',
                'string',
                'max:'.self::MAX_NAME_LENGTH,
                // 店舗の中で重複しない（無効なものとも重複しない）
                Rule::unique('seat_types', 'name')
                    ->where('shop_id', $this->shopId())
                    ->ignore($this->ignoreSeatTypeId()),
            ],
            'description' => ['bail', 'nullable', 'string', 'max:'.self::MAX_DESCRIPTION_LENGTH],
            'default_capacity' => ['bail', 'required', 'integer', 'min:1', 'max:'.self::MAX_CAPACITY],
            'default_price' => ['bail', 'required', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '名前を入力してください。',
            'name.string' => '名前を入力してください。',
            'name.max' => '名前は'.self::MAX_NAME_LENGTH.'文字以内で入力してください。',
            'name.unique' => self::DUPLICATE_NAME_MESSAGE,
            'description.string' => '説明の形式が正しくありません。',
            'description.max' => '説明は'.self::MAX_DESCRIPTION_LENGTH.'文字以内で入力してください。',
            'default_capacity.required' => '既定の席数を入力してください。',
            'default_capacity.integer' => '既定の席数は整数で入力してください。',
            'default_capacity.min' => '既定の席数は1以上で入力してください。',
            'default_capacity.max' => '既定の席数は'.self::MAX_CAPACITY.'以下で入力してください。',
            'default_price.required' => '既定の料金を入力してください。',
            'default_price.integer' => '既定の料金は整数で入力してください。',
            'default_price.min' => '既定の料金は0以上で入力してください。',
            'default_price.max' => '既定の料金は'.number_format(self::MAX_PRICE).'以下で入力してください。',
        ];
    }

    /**
     * 保存する項目（検証済みの値から必要な項目だけを取り出す）。shop_id は含めない。
     *
     * @return array{name: string, description: string|null, default_capacity: int, default_price: int}
     */
    public function seatTypeAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'default_capacity' => (int) $validated['default_capacity'],
            'default_price' => (int) $validated['default_price'],
        ];
    }
}
