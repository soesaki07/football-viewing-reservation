{{-- メッセージ。variant：success（成功）・error（エラー）・info（お知らせ）。messages を渡すと箇条書き、role は既定で error=alert・それ以外=status（画面を開いたときの案内は role="status" を渡す）、渡さなければ中身をそのまま出す。文言は {{ }} でエスケープされる --}}
@props(['variant' => 'info', 'messages' => [], 'role' => null])
@php
    $colorClass = match ($variant) {
        'success' => 'border-tertiary-fixed-dim bg-tertiary-fixed text-on-tertiary-fixed',
        'error' => 'border-error bg-error-container text-on-error-container',
        default => 'border-outline-variant bg-surface-container text-on-surface',
    };
@endphp
<div role="{{ $role ?? ($variant === 'error' ? 'alert' : 'status') }}" data-alert="{{ $variant }}" {{ $attributes->merge(['class' => 'rounded-xl border p-4 text-sm font-semibold '.$colorClass]) }}>
    @if (count($messages) > 0)
        <ul class="list-inside list-disc space-y-1">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @else
        {{ $slot }}
    @endif
</div>
