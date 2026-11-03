@extends('layouts.app')
@section('title', '予約詳細')
@section('content')
@php
    $broadcast = $reservation->broadcastSeatType->broadcast;
    $match = $broadcast->footballMatch;
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">🎫</span>
        予約詳細
    </h1>

    @if (session('success'))
        <p class="rounded-xl border border-pitch-100 bg-pitch-50 p-4 text-sm font-bold text-pitch-950" role="status">{{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ session('error') }}</p>
    @endif
    @error('status')
        <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <div class="space-y-6 rounded-xl border border-pitch-100 bg-white p-6 shadow-sm">
        <dl class="grid gap-3 text-sm text-pitch-700 sm:grid-cols-[8rem_1fr]">
            <dt class="font-semibold text-pitch-500">予約コード</dt>
            <dd class="text-2xl font-extrabold tracking-widest text-pitch-950">{{ $reservation->reservation_code }}</dd>
            <dt class="font-semibold text-pitch-500">ユーザー</dt>
            <dd>{{ $reservation->user->name }}</dd>
            <dt class="font-semibold text-pitch-500">試合</dt>
            <dd>
                <span class="font-bold text-pitch-950">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}</span><br>
                <span class="text-xs">{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</span>
            </dd>
            <dt class="font-semibold text-pitch-500">予約状態</dt>
            <dd>
                <span class="rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold text-pitch-950">{{ $reservation->statusLabel() }}</span>
                @foreach ($reservation->availableVisitStatusChanges() as $nextStatus)
                    <form method="POST" action="{{ route('owner.reservations.visit-status', $reservation) }}" data-confirm="予約 {{ $reservation->reservation_code }} を{{ \App\Models\Reservation::visitStatusLabel($nextStatus) }}にしますか？" class="ml-2 inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                        <input type="hidden" name="return" value="show">
                        @foreach (['period', 'match', 'page'] as $key)
                            @isset($listConditions[$key])
                                <input type="hidden" name="{{ $key }}" value="{{ $listConditions[$key] }}">
                            @endisset
                        @endforeach
                        <button type="submit" class="rounded-md border border-pitch-100 bg-white px-3 py-1 text-xs font-bold text-pitch-700 transition hover:text-gold-500">{{ $reservation->visitStatusButtonLabel($nextStatus) }}</button>
                    </form>
                @endforeach
            </dd>
        </dl>

        <dl class="grid gap-3 border-t border-pitch-100 pt-4 text-sm text-pitch-700 sm:grid-cols-[8rem_1fr]">
            <dt class="font-semibold text-pitch-500">座席</dt>
            <dd>{{ $reservation->broadcastSeatType->seatType->name }}</dd>
            <dt class="font-semibold text-pitch-500">人数</dt>
            <dd>{{ $reservation->number_of_people }}人</dd>
            <dt class="font-semibold text-pitch-500">単価</dt>
            <dd>{{ number_format($reservation->unit_price) }}円</dd>
            <dt class="font-semibold text-pitch-500">合計金額</dt>
            <dd class="text-lg font-extrabold text-pitch-950">{{ number_format($reservation->total_price) }}円</dd>
            <dt class="font-semibold text-pitch-500">備考</dt>
            <dd class="whitespace-pre-line">{{ $reservation->customer_note !== null && $reservation->customer_note !== '' ? $reservation->customer_note : 'なし' }}</dd>
        </dl>

        <dl class="grid gap-3 border-t border-pitch-100 pt-4 text-sm text-pitch-700 sm:grid-cols-[8rem_1fr]">
            <dt class="font-semibold text-pitch-500">予約日時</dt>
            <dd>{{ $reservation->reserved_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
            @if ($reservation->status === \App\Models\Reservation::STATUS_VISITED && $reservation->visited_at)
                <dt class="font-semibold text-pitch-500">来店日時</dt>
                <dd>{{ $reservation->visited_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
            @endif
            @if ($reservation->status === \App\Models\Reservation::STATUS_CANCELLED && $reservation->cancelled_at)
                <dt class="font-semibold text-pitch-500">キャンセル日時</dt>
                <dd>{{ $reservation->cancelled_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
            @endif
        </dl>

        <div class="text-sm font-semibold text-pitch-700">
            <a href="{{ route('owner.reservations.index', $listConditions) }}" class="transition hover:text-gold-500">一覧に戻る</a>
        </div>
    </div>
</div>
@endsection
