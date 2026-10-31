@extends('layouts.app')
@section('title', '予約確認')
@section('content')
<h1 class="mb-6 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
    <span class="text-gold-500">🍺</span>
    予約確認
</h1>
<div class="mx-auto max-w-3xl space-y-6 rounded-xl border border-pitch-100 bg-white p-6 shadow-sm">
    @include('reservations._match_summary', ['footballMatch' => $broadcast->footballMatch, 'shop' => $broadcast->shop])

    <dl class="grid gap-3 border-t border-pitch-100 pt-4 text-sm text-pitch-700 sm:grid-cols-[8rem_1fr]">
        <dt class="font-semibold text-pitch-500">座席</dt>
        <dd>{{ $seatType->seatType->name }}</dd>
        <dt class="font-semibold text-pitch-500">人数</dt>
        <dd>{{ $numberOfPeople }}人</dd>
        <dt class="font-semibold text-pitch-500">単価</dt>
        <dd>{{ number_format($seatType->price) }}円</dd>
        <dt class="font-semibold text-pitch-500">合計金額</dt>
        <dd class="text-lg font-extrabold text-pitch-950">{{ number_format($totalPrice) }}円</dd>
        @if ($customerNote !== null && $customerNote !== '')
            <dt class="font-semibold text-pitch-500">備考</dt>
            <dd class="whitespace-pre-line">{{ $customerNote }}</dd>
        @endif
    </dl>

    <div class="flex items-center gap-4">
        <form method="POST" action="{{ route('reservations.store', $broadcast) }}">
            @csrf
            <button type="submit" class="rounded-full bg-pitch-950 px-6 py-2 text-sm font-bold text-gold-400 transition hover:text-gold-300">予約確定</button>
        </form>
        <a href="{{ route('reservations.create', $broadcast) }}" class="text-sm font-semibold text-pitch-700 transition hover:text-gold-500">戻る（入力し直す）</a>
    </div>
</div>
@endsection
