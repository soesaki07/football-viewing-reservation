<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateShopRequest extends FormRequest
{
    public const MAX_NAME_LENGTH = 100;

    public const MAX_CITY_LENGTH = 100;

    public const MAX_ADDRESS_LINE_LENGTH = 255;

    public const MAX_PHONE_NUMBER_LENGTH = 30;

    public const MAX_DESCRIPTION_LENGTH = 2000;

    public const MAX_WEBSITE_URL_LENGTH = 2048;

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
     * 前後の空白の除去と、空文字の null への変換は、Laravel 標準のミドルウェアが行う。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 配列などの型の崩れた入力は、bail で後続のルールに進ませずに入力エラーにする
        return [
            'name' => ['bail', 'required', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'postal_code' => ['bail', 'nullable', 'string', 'regex:/^\d{3}-?\d{4}$/'],
            'prefecture' => ['bail', 'nullable', 'string', Rule::in(Shop::PREFECTURES)],
            'city' => ['bail', 'nullable', 'string', 'max:'.self::MAX_CITY_LENGTH],
            'address_line' => ['bail', 'required', 'string', 'max:'.self::MAX_ADDRESS_LINE_LENGTH],
            'phone_number' => ['bail', 'nullable', 'string', 'max:'.self::MAX_PHONE_NUMBER_LENGTH, 'regex:/^[0-9-]+$/', 'regex:/[0-9]/'],
            'description' => ['bail', 'nullable', 'string', 'max:'.self::MAX_DESCRIPTION_LENGTH],
            // 開店・閉店は両方入れるか両方空。同じ時刻は不可（閉店が開店より前なら翌日扱い）
            'opening_time' => ['bail', 'nullable', 'required_with:closing_time', 'string', 'date_format:H:i', 'different:closing_time'],
            'closing_time' => ['bail', 'nullable', 'required_with:opening_time', 'string', 'date_format:H:i'],
            'website_url' => ['bail', 'nullable', 'string', 'max:'.self::MAX_WEBSITE_URL_LENGTH, 'url:http,https'],
            'status' => ['bail', 'required', 'string', Rule::in([Shop::STATUS_DRAFT, Shop::STATUS_PUBLISHED])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '店名を入力してください。',
            'name.string' => '店名の形式が正しくありません。',
            'name.max' => '店名は'.self::MAX_NAME_LENGTH.'文字以内で入力してください。',
            'postal_code.string' => '郵便番号の形式が正しくありません。',
            'postal_code.regex' => '郵便番号は「123-4567」の形式で入力してください。',
            'prefecture.string' => '都道府県の形式が正しくありません。',
            'prefecture.in' => '都道府県を選択してください。',
            'city.string' => '市区町村の形式が正しくありません。',
            'city.max' => '市区町村は'.self::MAX_CITY_LENGTH.'文字以内で入力してください。',
            'address_line.required' => '番地以降を入力してください。',
            'address_line.string' => '番地以降の形式が正しくありません。',
            'address_line.max' => '番地以降は'.self::MAX_ADDRESS_LINE_LENGTH.'文字以内で入力してください。',
            'phone_number.string' => '電話番号の形式が正しくありません。',
            'phone_number.max' => '電話番号は'.self::MAX_PHONE_NUMBER_LENGTH.'文字以内で入力してください。',
            'phone_number.regex' => '電話番号は数字とハイフンで入力してください。',
            'description.string' => '説明の形式が正しくありません。',
            'description.max' => '説明は'.self::MAX_DESCRIPTION_LENGTH.'文字以内で入力してください。',
            'opening_time.required_with' => '開店時刻と閉店時刻は、両方入力するか両方空にしてください。',
            'opening_time.string' => '開店時刻の形式が正しくありません。',
            'opening_time.date_format' => '開店時刻は「17:00」の形式で入力してください。',
            'opening_time.different' => '開店時刻と閉店時刻は、違う時刻にしてください。',
            'closing_time.required_with' => '開店時刻と閉店時刻は、両方入力するか両方空にしてください。',
            'closing_time.string' => '閉店時刻の形式が正しくありません。',
            'closing_time.date_format' => '閉店時刻は「23:00」の形式で入力してください。',
            'website_url.string' => 'Webサイトの形式が正しくありません。',
            'website_url.max' => 'Webサイトは'.self::MAX_WEBSITE_URL_LENGTH.'文字以内で入力してください。',
            'website_url.url' => 'WebサイトはhttpまたはhttpsのURLで入力してください。',
            'status.required' => '公開状態を選択してください。',
            'status.string' => '公開状態を選択してください。',
            'status.in' => '公開状態を選択してください。',
        ];
    }

    /**
     * 保存する項目（検証済みの値から必要な項目だけを取り出す）。
     * user_id・緯度経度・google_place_id などは含めない。
     *
     * @return array<string, string|null>
     */
    public function shopAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            // 保存は「123-4567」の形にそろえる
            'postal_code' => Shop::normalizePostalCode($validated['postal_code'] ?? null),
            'prefecture' => $validated['prefecture'] ?? null,
            'city' => $validated['city'] ?? null,
            'address_line' => $validated['address_line'],
            'phone_number' => $validated['phone_number'] ?? null,
            'description' => $validated['description'] ?? null,
            // 保存は DB の値と同じ H:i:s の形にそろえる
            'opening_time' => isset($validated['opening_time']) ? $validated['opening_time'].':00' : null,
            'closing_time' => isset($validated['closing_time']) ? $validated['closing_time'].':00' : null,
            'website_url' => $validated['website_url'] ?? null,
            'status' => $validated['status'],
        ];
    }
}
