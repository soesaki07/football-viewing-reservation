@extends('layouts.app')
@section('title', '座席種別を登録')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">💺</span>
        座席種別を登録
    </h1>

    @if ($shops->isEmpty())
        <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">店舗が登録されていません。</p>
    @else
        {{-- 店舗の切り替え（2店舗以上のときだけ） --}}
        @if ($shops->count() >= 2)
            <nav class="flex flex-wrap gap-2" aria-label="店舗の切り替え">
                @foreach ($shops as $ownedShop)
                    <a href="{{ route('owner.seat-types.create', ['shop' => $ownedShop->id]) }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm font-bold transition',
                            'bg-pitch-800 text-white' => $ownedShop->id === $shop->id,
                            'border border-pitch-100 bg-white text-pitch-700 hover:text-gold-500' => $ownedShop->id !== $shop->id,
                        ])
                        @if ($ownedShop->id === $shop->id) aria-current="page" @endif>{{ $ownedShop->name }}</a>
                @endforeach
            </nav>
        @endif

        <p class="text-sm font-semibold text-pitch-500">店舗：<span class="text-pitch-950">{{ $shop->name }}</span></p>

        <form method="POST" action="{{ route('owner.seat-types.store') }}" class="space-y-6 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $shop->id }}">

            @include('owner.seat-types._form')

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-md bg-pitch-800 px-6 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">登録する</button>
                <a href="{{ route('owner.seat-types.index', ['shop' => $shop->id]) }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">一覧に戻る</a>
            </div>
        </form>
    @endif
</div>
@endsection
