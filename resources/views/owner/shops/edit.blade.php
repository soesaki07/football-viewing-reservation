@extends('layouts.app')
@section('title', '店舗情報を編集')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">🍺</span>
        店舗情報を編集
    </h1>

    {{-- 公開中の店舗を非公開にして保存するときだけ、確認ダイアログを出す（画面を開いたときの公開状態で決める） --}}
    <form method="POST" action="{{ route('owner.shops.update', $shop) }}"
        @if ($shop->isPublished()) data-confirm="この店舗を非公開にしますか？店舗の詳細ページが見られなくなり、新しい予約ができなくなります（すでに入っている予約はそのまま有効です）。" data-confirm-field="status" data-confirm-value="{{ \App\Models\Shop::STATUS_DRAFT }}" @endif
        class="space-y-6 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-semibold text-pitch-500">店名（{{ \App\Http\Requests\UpdateShopRequest::MAX_NAME_LENGTH }}文字以内）</label>
            <input type="text" id="name" name="name" required maxlength="{{ \App\Http\Requests\UpdateShopRequest::MAX_NAME_LENGTH }}" value="{{ old('name', $shop->name) }}"
                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
            @error('name')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="postal_code" class="block text-sm font-semibold text-pitch-500">郵便番号（任意・例：123-4567）</label>
                <input type="text" id="postal_code" name="postal_code" inputmode="numeric" maxlength="8" value="{{ old('postal_code', \App\Models\Shop::normalizePostalCode($shop->postal_code)) }}"
                    class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                @error('postal_code')
                    <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="prefecture" class="block text-sm font-semibold text-pitch-500">都道府県（任意）</label>
                <select id="prefecture" name="prefecture" class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                    <option value="">選択しない</option>
                    @foreach (\App\Models\Shop::PREFECTURES as $prefecture)
                        <option value="{{ $prefecture }}" @selected(old('prefecture', $shop->prefecture) === $prefecture)>{{ $prefecture }}</option>
                    @endforeach
                </select>
                @error('prefecture')
                    <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="city" class="block text-sm font-semibold text-pitch-500">市区町村（任意・{{ \App\Http\Requests\UpdateShopRequest::MAX_CITY_LENGTH }}文字以内）</label>
            <input type="text" id="city" name="city" maxlength="{{ \App\Http\Requests\UpdateShopRequest::MAX_CITY_LENGTH }}" value="{{ old('city', $shop->city) }}"
                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
            @error('city')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="address_line" class="block text-sm font-semibold text-pitch-500">番地以降（{{ \App\Http\Requests\UpdateShopRequest::MAX_ADDRESS_LINE_LENGTH }}文字以内）</label>
            <input type="text" id="address_line" name="address_line" required maxlength="{{ \App\Http\Requests\UpdateShopRequest::MAX_ADDRESS_LINE_LENGTH }}" value="{{ old('address_line', $shop->address_line) }}"
                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
            @error('address_line')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="phone_number" class="block text-sm font-semibold text-pitch-500">電話番号（任意・数字とハイフン）</label>
            <input type="tel" id="phone_number" name="phone_number" maxlength="{{ \App\Http\Requests\UpdateShopRequest::MAX_PHONE_NUMBER_LENGTH }}" value="{{ old('phone_number', $shop->phone_number) }}"
                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
            @error('phone_number')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-semibold text-pitch-500">説明（任意・{{ \App\Http\Requests\UpdateShopRequest::MAX_DESCRIPTION_LENGTH }}文字以内）</label>
            <textarea id="description" name="description" rows="5" maxlength="{{ \App\Http\Requests\UpdateShopRequest::MAX_DESCRIPTION_LENGTH }}"
                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">{{ old('description', $shop->description) }}</textarea>
            @error('description')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="opening_time" class="block text-sm font-semibold text-pitch-500">開店時刻（任意）</label>
                <input type="time" id="opening_time" name="opening_time" value="{{ old('opening_time', $shop->opening_time !== null ? substr($shop->opening_time, 0, 5) : '') }}"
                    class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                @error('opening_time')
                    <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="closing_time" class="block text-sm font-semibold text-pitch-500">閉店時刻（任意）</label>
                <input type="time" id="closing_time" name="closing_time" value="{{ old('closing_time', $shop->closing_time !== null ? substr($shop->closing_time, 0, 5) : '') }}"
                    class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                @error('closing_time')
                    <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <p class="text-xs text-pitch-500">営業時間は、開店・閉店を両方入力するか、両方空にしてください。閉店が開店より前の時刻なら、翌日の閉店として表示されます。</p>

        <div>
            <label for="website_url" class="block text-sm font-semibold text-pitch-500">Webサイト（任意・httpまたはhttpsのURL）</label>
            <input type="url" id="website_url" name="website_url" maxlength="{{ \App\Http\Requests\UpdateShopRequest::MAX_WEBSITE_URL_LENGTH }}" value="{{ old('website_url', $shop->website_url) }}"
                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
            @error('website_url')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-semibold text-pitch-500">公開状態</label>
            <select id="status" name="status" required class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                <option value="{{ \App\Models\Shop::STATUS_DRAFT }}" @selected(old('status', $shop->status) === \App\Models\Shop::STATUS_DRAFT)>非公開</option>
                <option value="{{ \App\Models\Shop::STATUS_PUBLISHED }}" @selected(old('status', $shop->status) === \App\Models\Shop::STATUS_PUBLISHED)>公開</option>
            </select>
            @error('status')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-pitch-500">非公開にすると、店舗の詳細ページが見られなくなり、新しい予約ができなくなります。すでに入っている予約はそのまま有効です。</p>
        </div>

        <p class="text-xs text-pitch-500">住所（郵便番号・都道府県・市区町村・番地以降）を変更すると、登録済みの位置情報（緯度経度）は消え、地図は住所から表示されます。</p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-pitch-800 px-6 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">更新する</button>
            <a href="{{ route('owner.shops.index') }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">一覧に戻る</a>
        </div>
    </form>
</div>
@endsection
