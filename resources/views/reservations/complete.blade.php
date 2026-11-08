@extends('layouts.app')
@section('title', '予約完了')
@section('content')
<div class="mx-auto max-w-3xl">
    <x-reservation.steps :current="3" class="mb-6" />

    <x-page-header title="予約が完了しました" description="ご予約ありがとうございます。予約コードは来店時にお伝えください。" />

    <x-card class="space-y-6 sm:p-6">
        <div class="rounded-xl bg-primary-container p-6 text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-secondary-container">予約コード</p>
            <p class="mt-2 break-all font-headline text-3xl font-bold tracking-widest text-on-primary sm:text-4xl" data-testid="reservation-code">{{ $reservation->reservation_code }}</p>
        </div>

        <x-reservation.match-summary :football-match="$broadcast->footballMatch" :shop="$shop" />

        <x-reservation.summary
            :seat-name="$reservation->broadcastSeatType->seatType->name"
            :number-of-people="$reservation->number_of_people"
            :unit-price="$reservation->unit_price"
            :total-price="$reservation->total_price"
            :note="$reservation->customer_note" />

        <div class="flex flex-col gap-3 border-t border-outline-variant/50 pt-4 sm:flex-row sm:items-center">
            <x-button :href="route('mypage')">マイページ</x-button>
            <x-button variant="secondary" :href="route('matches.show', $broadcast->football_match_id)">試合詳細に戻る</x-button>
            <x-button variant="secondary" :href="route('matches.index')">試合一覧へ</x-button>
        </div>
    </x-card>
</div>
@endsection
