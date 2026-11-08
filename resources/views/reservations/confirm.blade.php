@extends('layouts.app')
@section('title', '予約確認')
@section('content')
<div class="mx-auto max-w-3xl">
    <x-reservation.steps :current="2" class="mb-6" />

    <x-page-header title="予約確認" />

    <x-card class="space-y-6 sm:p-6">
        <x-reservation.match-summary :football-match="$broadcast->footballMatch" :shop="$broadcast->shop" />

        <x-reservation.summary
            :seat-name="$seatType->seatType->name"
            :number-of-people="$numberOfPeople"
            :unit-price="$seatType->price"
            :total-price="$totalPrice"
            :note="$customerNote" />

        <div class="flex flex-col-reverse gap-3 border-t border-outline-variant/50 pt-4 sm:flex-row sm:items-center sm:justify-end">
            <x-button variant="secondary" :href="route('reservations.create', $broadcast)">戻る（入力し直す）</x-button>
            <form method="POST" action="{{ route('reservations.store', $broadcast) }}" class="flex">
                @csrf
                <x-button type="submit" class="w-full">予約確定</x-button>
            </form>
        </div>
    </x-card>
</div>
@endsection
