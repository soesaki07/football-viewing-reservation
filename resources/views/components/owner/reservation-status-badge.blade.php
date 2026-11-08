{{-- 予約状態のバッジ。予約確定・来店済み=success、来店なし=danger、キャンセル済み=neutral。$reservation：予約 --}}
@props(['reservation'])
@php
    $variant = match ($reservation->status) {
        \App\Models\Reservation::STATUS_CONFIRMED, \App\Models\Reservation::STATUS_VISITED => 'success',
        \App\Models\Reservation::STATUS_NO_SHOW => 'danger',
        default => 'neutral',
    };
@endphp
<x-badge :variant="$variant" {{ $attributes }}>{{ $reservation->statusLabel() }}</x-badge>
