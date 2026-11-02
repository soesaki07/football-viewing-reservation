{{-- 座席種別の登録・編集で共通の入力欄。$seatType は編集のときだけ渡す --}}
<div>
    <label for="name" class="block text-sm font-semibold text-pitch-500">名前（{{ \App\Http\Requests\SeatTypeRequest::MAX_NAME_LENGTH }}文字以内）</label>
    <input type="text" id="name" name="name" required maxlength="{{ \App\Http\Requests\SeatTypeRequest::MAX_NAME_LENGTH }}"
        value="{{ old('name', $seatType->name ?? '') }}"
        class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
    @error('name')
        <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="description" class="block text-sm font-semibold text-pitch-500">説明（任意・{{ \App\Http\Requests\SeatTypeRequest::MAX_DESCRIPTION_LENGTH }}文字以内）</label>
    <textarea id="description" name="description" rows="3" maxlength="{{ \App\Http\Requests\SeatTypeRequest::MAX_DESCRIPTION_LENGTH }}"
        class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">{{ old('description', $seatType->description ?? '') }}</textarea>
    @error('description')
        <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
    @enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="default_capacity" class="block text-sm font-semibold text-pitch-500">既定の席数</label>
        <input type="number" id="default_capacity" name="default_capacity" required min="1" max="{{ \App\Http\Requests\SeatTypeRequest::MAX_CAPACITY }}"
            value="{{ old('default_capacity', $seatType->default_capacity ?? '') }}"
            class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
        @error('default_capacity')
            <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="default_price" class="block text-sm font-semibold text-pitch-500">既定の料金（円）</label>
        <input type="number" id="default_price" name="default_price" required min="0" max="{{ \App\Http\Requests\SeatTypeRequest::MAX_PRICE }}"
            value="{{ old('default_price', $seatType->default_price ?? '') }}"
            class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
        @error('default_price')
            <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
        @enderror
    </div>
</div>
<p class="text-xs text-pitch-500">既定の席数・料金は、放映を新しく登録するときの初期値です。変更しても、登録済みの放映の席数・料金は変わりません。</p>
