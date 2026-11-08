{{-- 店舗の登録・編集で共通の入力欄（$shop：編集する店舗。登録のときは空の Shop）。項目のまとまりごとに見出しを付ける --}}
<x-owner.form-section title="基本情報">
    <x-form.field name="name" :label="'店名（'.\App\Http\Requests\ShopRequest::MAX_NAME_LENGTH.'文字以内）'" :value="$shop->name"
        required maxlength="{{ \App\Http\Requests\ShopRequest::MAX_NAME_LENGTH }}" />
    <x-form.field name="description" type="textarea" :label="'説明（任意・'.\App\Http\Requests\ShopRequest::MAX_DESCRIPTION_LENGTH.'文字以内）'" :value="$shop->description"
        rows="5" maxlength="{{ \App\Http\Requests\ShopRequest::MAX_DESCRIPTION_LENGTH }}" />
</x-owner.form-section>

<x-owner.form-section title="所在地">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.field name="postal_code" label="郵便番号（任意・例：123-4567）" :value="\App\Models\Shop::normalizePostalCode($shop->postal_code)"
            inputmode="numeric" maxlength="8" />
        <x-form.field name="prefecture" type="select" label="都道府県（任意）">
            <option value="">選択しない</option>
            @foreach (\App\Models\Shop::PREFECTURES as $prefecture)
                <option value="{{ $prefecture }}" @selected(old('prefecture', $shop->prefecture) === $prefecture)>{{ $prefecture }}</option>
            @endforeach
        </x-form.field>
    </div>
    <x-form.field name="city" :label="'市区町村（任意・'.\App\Http\Requests\ShopRequest::MAX_CITY_LENGTH.'文字以内）'" :value="$shop->city"
        maxlength="{{ \App\Http\Requests\ShopRequest::MAX_CITY_LENGTH }}" />
    <x-form.field name="address_line" :label="'番地以降（'.\App\Http\Requests\ShopRequest::MAX_ADDRESS_LINE_LENGTH.'文字以内）'" :value="$shop->address_line"
        required maxlength="{{ \App\Http\Requests\ShopRequest::MAX_ADDRESS_LINE_LENGTH }}" />
</x-owner.form-section>

<x-owner.form-section title="営業時間と連絡先">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.field name="opening_time" type="time" label="開店時刻（任意）" :value="$shop->opening_time !== null ? substr($shop->opening_time, 0, 5) : ''" />
        <x-form.field name="closing_time" type="time" label="閉店時刻（任意）" :value="$shop->closing_time !== null ? substr($shop->closing_time, 0, 5) : ''" />
    </div>
    <p class="text-xs text-on-surface-variant">営業時間は、開店・閉店を両方入力するか、両方空にしてください。閉店が開店より前の時刻なら、翌日の閉店として表示されます。</p>
    <x-form.field name="phone_number" type="tel" label="電話番号（任意・数字とハイフン）" :value="$shop->phone_number"
        maxlength="{{ \App\Http\Requests\ShopRequest::MAX_PHONE_NUMBER_LENGTH }}" />
    <x-form.field name="website_url" type="url" label="Webサイト（任意・httpまたはhttpsのURL）" :value="$shop->website_url"
        maxlength="{{ \App\Http\Requests\ShopRequest::MAX_WEBSITE_URL_LENGTH }}" />
</x-owner.form-section>
