{{-- ボタン。variant：primary（主）・secondary（副）・danger（キャンセルなど）。size：md・sm。href があればリンク、なければ button。
    href は http・https・相対パス（/ で始まる、またはスキームのないもの）だけ受け付ける。それ以外（javascript: や data: など、空白・制御文字・バックスラッシュ（\）を含むもの）は href を出さない（入力値の URL を渡しても XSS にならないように） --}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit'])
@php
    $safeHref = null;
    if ($href !== null && $href !== '' && ! preg_match('/[\x00-\x20\x7f\\\\]/', $href) && ! str_starts_with($href, '//')
        && (preg_match('#^https?://#i', $href) || preg_match('#^[^:/?\#]*([/?\#]|$)#', $href))) {
        $safeHref = $href;
    }
    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-lg font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary',
        match ($size) {
            'sm' => 'px-3 py-1.5 text-xs',
            default => 'px-6 py-2.5 text-sm',
        },
        match ($variant) {
            'secondary' => 'bg-surface-container text-primary hover:bg-surface-container-high',
            'danger' => 'border border-error bg-surface-container-lowest text-error hover:bg-error-container',
            default => 'bg-primary-container text-on-primary hover:bg-primary',
        },
    ]);
@endphp
@if ($href !== null)
    <a @if ($safeHref !== null) href="{{ $safeHref }}" @endif {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
