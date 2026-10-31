@extends('layouts.app')
@section('title', '予約詳細')
@section('content')
<h1 class="mb-6 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
    <span class="text-gold-500">🎫</span>
    予約詳細
</h1>
<div class="mx-auto max-w-3xl space-y-6 rounded-xl border border-pitch-100 bg-white p-6 shadow-sm">
    <dl class="grid gap-3 text-sm text-pitch-700 sm:grid-cols-[8rem_1fr]">
        <dt class="font-semibold text-pitch-500">予約コード</dt>
        <dd class="text-2xl font-extrabold tracking-widest text-pitch-950">{{ $reservation->reservation_code }}</dd>
        <dt class="font-semibold text-pitch-500">予約状態</dt>
        <dd class="font-bold text-pitch-950">{{ $reservation->statusLabel() }}</dd>
    </dl>

    @include('reservations._match_summary', ['footballMatch' => $broadcast->footballMatch, 'shop' => $shop])

    <dl class="grid gap-3 border-t border-pitch-100 pt-4 text-sm text-pitch-700 sm:grid-cols-[8rem_1fr]">
        <dt class="font-semibold text-pitch-500">座席</dt>
        <dd>{{ $reservation->broadcastSeatType->seatType->name }}</dd>
        <dt class="font-semibold text-pitch-500">人数</dt>
        <dd>{{ $reservation->number_of_people }}人</dd>
        <dt class="font-semibold text-pitch-500">合計金額</dt>
        <dd class="text-lg font-extrabold text-pitch-950">{{ number_format($reservation->total_price) }}円</dd>
        @if ($reservation->customer_note)
            <dt class="font-semibold text-pitch-500">備考</dt>
            <dd class="whitespace-pre-line">{{ $reservation->customer_note }}</dd>
        @endif
    </dl>

    <div class="flex flex-wrap gap-4 text-sm font-semibold text-pitch-700">
        <a href="{{ route('mypage') }}" class="transition hover:text-gold-500">マイページに戻る</a>
        <a href="{{ route('matches.show', $broadcast->football_match_id) }}" class="transition hover:text-gold-500">試合詳細へ</a>
    </div>
</div>
@endsection
