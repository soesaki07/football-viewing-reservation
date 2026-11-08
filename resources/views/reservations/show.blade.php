@extends('layouts.app')
@section('title', '予約詳細')
@section('content')
<div class="mx-auto max-w-3xl">
    <x-page-header title="予約詳細" />

    @if (session('success'))
        <x-alert variant="success" class="mb-6">{{ session('success') }}</x-alert>
    @endif
    @if (session('error'))
        <x-alert variant="error" class="mb-6">{{ session('error') }}</x-alert>
    @endif

    <x-card class="space-y-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">予約コード</p>
                <p class="mt-1 break-all font-headline text-2xl font-bold tracking-widest text-primary" data-testid="reservation-code">{{ $reservation->reservation_code }}</p>
            </div>
            <div>
                <span class="sr-only">予約状態：</span>
                <x-reservation.status-badge :reservation="$reservation" class="px-3 py-1 text-sm" />
            </div>
        </div>

        <x-reservation.match-summary :football-match="$broadcast->footballMatch" :shop="$shop" :show-address="true" />

        <x-reservation.summary
            :seat-name="$reservation->broadcastSeatType->seatType->name"
            :number-of-people="$reservation->number_of_people"
            :unit-price="$reservation->unit_price"
            :total-price="$reservation->total_price"
            :note="$reservation->customer_note"
            :reserved-at="$reservation->reserved_at"
            :cancelled-at="$reservation->cancelled_at" />

        @if ($canCancel)
            <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" data-confirm="この予約をキャンセルしますか？" class="border-t border-outline-variant/50 pt-4">
                @csrf
                @method('PATCH')
                <x-button variant="danger" type="submit">この予約をキャンセルする</x-button>
            </form>
        @endif

        <div class="flex flex-col gap-3 border-t border-outline-variant/50 pt-4 sm:flex-row sm:items-center">
            <x-button variant="secondary" :href="route('mypage')">マイページに戻る</x-button>
            <x-button variant="secondary" :href="route('matches.show', $broadcast->football_match_id)">試合詳細へ</x-button>
        </div>
    </x-card>
</div>
@endsection
