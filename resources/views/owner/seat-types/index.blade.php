@extends('layouts.app')
@section('title', '座席管理')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">💺</span>
        座席管理
    </h1>

    @if ($shops->isEmpty())
        <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">店舗が登録されていません。</p>
    @else
        @if (session('success'))
            <x-alert variant="success">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->has('is_active'))
            <x-alert variant="error">{{ $errors->first('is_active') }}</x-alert>
        @endif

        {{-- 店舗の切り替え（2店舗以上のときだけ） --}}
        @if ($shops->count() >= 2)
            <nav class="flex flex-wrap gap-2" aria-label="店舗の切り替え">
                @foreach ($shops as $ownedShop)
                    <a href="{{ route('owner.seat-types.index', ['shop' => $ownedShop->id]) }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm font-bold transition',
                            'bg-pitch-800 text-white' => $ownedShop->id === $shop->id,
                            'border border-pitch-100 bg-white text-pitch-700 hover:text-gold-500' => $ownedShop->id !== $shop->id,
                        ])
                        @if ($ownedShop->id === $shop->id) aria-current="page" @endif>{{ $ownedShop->name }}</a>
                @endforeach
            </nav>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-pitch-500">店舗：<span class="text-pitch-950">{{ $shop->name }}</span></p>
            <a href="{{ route('owner.seat-types.create', ['shop' => $shop->id]) }}" class="rounded-md bg-pitch-800 px-4 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">座席種別を登録する</a>
        </div>

        @if ($seatTypes->isEmpty())
            <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">座席種別はまだありません。</p>
        @else
            {{-- スマホ幅ではテーブルの中だけを横スクロールさせる --}}
            <div class="overflow-x-auto rounded-xl border border-pitch-100 bg-white shadow-sm">
                <table class="min-w-full text-left text-sm text-pitch-700">
                    <thead class="bg-pitch-50 text-xs font-semibold text-pitch-500">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">名前</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">説明</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">既定の席数</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">既定の料金</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">状態</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">使っている放映</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3"><span class="sr-only">操作</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pitch-100">
                        @foreach ($seatTypes as $seatType)
                            <tr>
                                <td class="min-w-32 px-4 py-3 font-bold text-pitch-950">{{ $seatType->name }}</td>
                                <td class="min-w-48 px-4 py-3 text-xs">{{ $seatType->description }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $seatType->default_capacity }}席</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ number_format($seatType->default_price) }}円</td>
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold text-pitch-950">{{ $seatType->is_active ? '有効' : '無効' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $seatType->broadcast_seat_types_count }}件</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('owner.seat-types.edit', $seatType) }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">編集</a>
                                    @if ($seatType->is_active)
                                        <form method="POST" action="{{ route('owner.seat-types.active', $seatType) }}" data-confirm="「{{ $seatType->name }}」を無効にしますか？どの放映でも、この座席の新しい予約ができなくなります（すでに入っている予約はそのまま有効です）。" class="ml-2 inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="0">
                                            <button type="submit" class="text-sm font-bold text-red-700 hover:text-gold-500">無効にする</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('owner.seat-types.active', $seatType) }}" class="ml-2 inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="1">
                                            <button type="submit" class="text-sm font-bold text-pitch-700 hover:text-gold-500">有効にする</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection
