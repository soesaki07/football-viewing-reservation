@extends('layouts.owner')
@section('title', '予約詳細')
@section('content')
@php
    $broadcast = $reservation->broadcastSeatType->broadcast;
    $match = $broadcast->footballMatch;
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <nav aria-label="パンくず" class="text-xs font-semibold text-on-surface-variant">
        <a href="{{ route('owner.reservations.index', $listConditions) }}" class="hover:text-primary">予約管理</a>
        <span aria-hidden="true">/</span>
        <span class="text-on-surface">予約詳細</span>
    </nav>

    <x-page-header title="予約詳細" eyebrow="Partner Console" />

    @if (session('success'))
        <x-alert variant="success">{{ session('success') }}</x-alert>
    @endif
    @if (session('error'))
        <x-alert variant="error">{{ session('error') }}</x-alert>
    @endif
    @error('status')
        <x-alert variant="error">{{ $message }}</x-alert>
    @enderror

    <x-card class="space-y-6">
        <dl class="grid gap-3 text-sm text-on-surface sm:grid-cols-[8rem_1fr]">
            <dt class="font-semibold text-on-surface-variant">予約コード</dt>
            <dd class="font-headline text-2xl font-extrabold tracking-widest text-primary">{{ $reservation->reservation_code }}</dd>
            <dt class="font-semibold text-on-surface-variant">ユーザー</dt>
            <dd>{{ $reservation->user->name }}</dd>
            <dt class="font-semibold text-on-surface-variant">試合</dt>
            <dd>
                <span class="font-bold">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}</span><br>
                <span class="text-xs text-on-surface-variant">{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</span>
            </dd>
            <dt class="font-semibold text-on-surface-variant">予約状態</dt>
            <dd class="flex flex-wrap items-center gap-2">
                <x-owner.reservation-status-badge :reservation="$reservation" />
                @foreach ($reservation->availableVisitStatusChanges() as $nextStatus)
                    <form method="POST" action="{{ route('owner.reservations.visit-status', $reservation) }}" data-confirm="予約 {{ $reservation->reservation_code }} を{{ \App\Models\Reservation::visitStatusLabel($nextStatus) }}にしますか？" class="inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                        <input type="hidden" name="return" value="show">
                        @foreach (['period', 'match', 'page'] as $key)
                            @isset($listConditions[$key])
                                <input type="hidden" name="{{ $key }}" value="{{ $listConditions[$key] }}">
                            @endisset
                        @endforeach
                        <x-button variant="secondary" size="sm">{{ $reservation->visitStatusButtonLabel($nextStatus) }}</x-button>
                    </form>
                @endforeach
            </dd>
        </dl>

        <dl class="grid gap-3 border-t border-outline-variant/60 pt-4 text-sm text-on-surface sm:grid-cols-[8rem_1fr]">
            <dt class="font-semibold text-on-surface-variant">座席</dt>
            <dd>{{ $reservation->broadcastSeatType->seatType->name }}</dd>
            <dt class="font-semibold text-on-surface-variant">人数</dt>
            <dd>{{ $reservation->number_of_people }}人</dd>
            <dt class="font-semibold text-on-surface-variant">単価</dt>
            <dd>{{ number_format($reservation->unit_price) }}円</dd>
            <dt class="font-semibold text-on-surface-variant">合計金額</dt>
            <dd class="text-lg font-extrabold text-primary">{{ number_format($reservation->total_price) }}円</dd>
            <dt class="font-semibold text-on-surface-variant">備考</dt>
            <dd class="whitespace-pre-line">{{ $reservation->customer_note !== null && $reservation->customer_note !== '' ? $reservation->customer_note : 'なし' }}</dd>
        </dl>

        <dl class="grid gap-3 border-t border-outline-variant/60 pt-4 text-sm text-on-surface sm:grid-cols-[8rem_1fr]">
            <dt class="font-semibold text-on-surface-variant">予約日時</dt>
            <dd>{{ $reservation->reserved_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
            @if ($reservation->status === \App\Models\Reservation::STATUS_VISITED && $reservation->visited_at)
                <dt class="font-semibold text-on-surface-variant">来店日時</dt>
                <dd>{{ $reservation->visited_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
            @endif
            @if ($reservation->status === \App\Models\Reservation::STATUS_CANCELLED && $reservation->cancelled_at)
                <dt class="font-semibold text-on-surface-variant">キャンセル日時</dt>
                <dd>{{ $reservation->cancelled_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
            @endif
        </dl>

        <div>
            <x-button variant="secondary" :href="route('owner.reservations.index', $listConditions)">一覧に戻る</x-button>
        </div>
    </x-card>
</div>
@endsection
