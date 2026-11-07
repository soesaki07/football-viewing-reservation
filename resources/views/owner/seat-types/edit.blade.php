@extends('layouts.app')
@section('title', '座席種別を編集')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">💺</span>
        座席種別を編集
    </h1>

    <p class="text-sm font-semibold text-pitch-500">店舗：<span class="text-pitch-950">{{ $shop->name }}</span></p>

    @unless ($seatType->is_active)
        <x-alert variant="error" role="status">この座席種別は無効です。有効にするには、一覧の「有効にする」を押してください。</x-alert>
    @endunless

    <form method="POST" action="{{ route('owner.seat-types.update', $seatType) }}" class="space-y-6 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
        @csrf
        @method('PUT')

        @include('owner.seat-types._form')

        <p class="text-xs text-pitch-500">この座席種別を使っている放映：{{ $seatType->broadcast_seat_types_count }}件</p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-pitch-800 px-6 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">更新する</button>
            <a href="{{ route('owner.seat-types.index', ['shop' => $shop->id]) }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">一覧に戻る</a>
        </div>
    </form>
</div>
@endsection
