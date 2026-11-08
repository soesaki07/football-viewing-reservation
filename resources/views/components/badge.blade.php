{{-- バッジ。variant：success（成功・公開・有効）・warning（注意）・danger（危険・無効）・neutral（中立・非公開） --}}
@props(['variant' => 'neutral'])
@php
    $colorClass = match ($variant) {
        'success' => 'bg-tertiary-fixed text-on-tertiary-fixed',
        'warning' => 'bg-secondary-fixed text-on-secondary-fixed',
        'danger' => 'bg-error-container text-on-error-container',
        default => 'bg-surface-container-high text-on-surface-variant',
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold '.$colorClass]) }}>{{ $slot }}</span>
