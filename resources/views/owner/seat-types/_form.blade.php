{{-- 座席種別の登録・編集で共通の入力欄。$seatType は編集のときだけ渡す --}}
<x-owner.form-section title="座席の情報">
    <x-form.field name="name" :label="'名前（'.\App\Http\Requests\SeatTypeRequest::MAX_NAME_LENGTH.'文字以内）'" :value="$seatType->name ?? ''"
        required maxlength="{{ \App\Http\Requests\SeatTypeRequest::MAX_NAME_LENGTH }}" />
    <x-form.field name="description" type="textarea" :label="'説明（任意・'.\App\Http\Requests\SeatTypeRequest::MAX_DESCRIPTION_LENGTH.'文字以内）'" :value="$seatType->description ?? ''"
        rows="3" maxlength="{{ \App\Http\Requests\SeatTypeRequest::MAX_DESCRIPTION_LENGTH }}" />
</x-owner.form-section>

<x-owner.form-section title="既定の席数と料金">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.field name="default_capacity" type="number" label="既定の席数" :value="$seatType->default_capacity ?? ''"
            required min="1" max="{{ \App\Http\Requests\SeatTypeRequest::MAX_CAPACITY }}" />
        <x-form.field name="default_price" type="number" label="既定の料金（円）" :value="$seatType->default_price ?? ''"
            required min="0" max="{{ \App\Http\Requests\SeatTypeRequest::MAX_PRICE }}" />
    </div>
    <p class="text-xs text-on-surface-variant">既定の席数・料金は、放映を新しく登録するときの初期値です。変更しても、登録済みの放映の席数・料金は変わりません。</p>
</x-owner.form-section>
