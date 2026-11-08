{{--
    入力欄（ラベル・入力欄・説明・その欄のエラーをひとまとめにする）。
    type：text・email・password・number・date・time・url・tel（input）、select（中身に option を書く）、textarea
    name：name 属性。エラーは同じ名前（seats[1][price] なら seats.1.price）から取る
    label：ラベル。hint：説明（任意）。value：初期値（input・textarea のみ。old() があればそちらを優先。password には出さない）
    select：value は使わない。呼び出す側が option に @selected(old('name', $current)) を書く（option は slot で受けるため、部品では選べない）
    showError：false にすると欄の下にエラーを出さない（画面の上にまとめて出すとき。aria-invalid は付く）
    id：省略すると name から作る。required・placeholder・pattern・min・max・step・rows などは、そのまま入力欄に渡る
--}}
@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null, 'id' => null, 'showError' => true])
@php
    $fieldId = $id ?? trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $name), '_');
    $errorKey = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $errorMessage = ($errors ?? new \Illuminate\Support\ViewErrorBag)->first($errorKey);
    $hasError = $errorMessage !== '';
    $describedBy = trim(($hasError && $showError ? $fieldId.'-error ' : '').($hint ? $fieldId.'-hint' : ''));
    $oldValue = old($errorKey, $value);
    $controlClass = 'w-full rounded-lg border bg-surface-container-lowest px-3 py-2.5 text-sm text-on-surface focus:outline-none focus:ring-2 '
        .($hasError ? 'border-error focus:ring-error' : 'border-outline-variant focus:border-primary-container focus:ring-secondary-container');
    $controlAttributes = $attributes->merge(['class' => $controlClass])->merge([
        'id' => $fieldId,
        'name' => $name,
        'aria-invalid' => $hasError ? 'true' : null,
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
    ]);
@endphp
<div class="flex flex-col gap-1.5">
    <label for="{{ $fieldId }}" class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ $label }}</label>
    @if ($type === 'select')
        <select {{ $controlAttributes }}>{{ $slot }}</select>
    @elseif ($type === 'textarea')
        <textarea {{ $controlAttributes }}>{{ $oldValue }}</textarea>
    @elseif ($type === 'password')
        <input type="password" {{ $controlAttributes }}>
    @else
        <input type="{{ $type }}" value="{{ $oldValue }}" {{ $controlAttributes }}>
    @endif
    @if ($hint)
        <p id="{{ $fieldId }}-hint" class="text-xs text-on-surface-variant">{{ $hint }}</p>
    @endif
    @if ($hasError && $showError)
        <p id="{{ $fieldId }}-error" class="text-xs font-semibold text-error">{{ $errorMessage }}</p>
    @endif
</div>
