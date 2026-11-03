<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateShopRequest extends ShopRequest
{
    /**
     * 自分の店舗だけ編集できる。他店は 404、shop_owner 以外は 403。
     * 入力の検証より先に認可する（他店の存在を隠すため）。
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->shop());

        return true;
    }

    /**
     * 編集する店舗（ルートモデルバインディング。削除済みは 404）。
     */
    public function shop(): Shop
    {
        /** @var Shop */
        return $this->route('shop');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            // 公開状態は編集のときだけ変えられる
            'status' => ['bail', 'required', 'string', Rule::in([Shop::STATUS_DRAFT, Shop::STATUS_PUBLISHED])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'status.required' => '公開状態を選択してください。',
            'status.string' => '公開状態を選択してください。',
            'status.in' => '公開状態を選択してください。',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function shopAttributes(): array
    {
        return [
            ...parent::shopAttributes(),
            'status' => $this->validated()['status'],
        ];
    }
}
