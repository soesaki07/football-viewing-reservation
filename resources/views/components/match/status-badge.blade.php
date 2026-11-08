{{-- 試合の状態バッジ（開催前・LIVE・終了など）。match：FootballMatch。ラベルは FootballMatch::statusInfo() を使い、色だけをここで決める --}}
@props(['match'])
@php
    $variant = match ($match->status) {
        'SCHEDULED', 'TIMED' => 'success',
        'IN_PLAY', 'PAUSED' => 'danger',
        'POSTPONED', 'SUSPENDED' => 'warning',
        default => 'neutral',
    };
@endphp
<x-badge :variant="$variant" {{ $attributes->class(['animate-pulse' => $match->status === 'IN_PLAY']) }}>{{ $match->statusInfo()['label'] }}</x-badge>
