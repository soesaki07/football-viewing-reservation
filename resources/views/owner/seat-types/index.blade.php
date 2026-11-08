@extends('layouts.owner')
@section('title', '座席管理')
@if (! $shops->isEmpty())
@section('shop-switcher')
<x-owner.shop-switcher :shops="$shops" :current="$shop" />
@endsection
@endif
@section('content')
<div class="space-y-6">
    @if ($shops->isEmpty())
        <x-page-header title="座席管理" eyebrow="Partner Console" />
        <x-card class="text-sm text-on-surface-variant">店舗が登録されていません。</x-card>
    @else
        <x-page-header title="座席管理" eyebrow="Partner Console">
            <x-button :href="route('owner.seat-types.create', ['shop' => $shop->id])">座席種別を登録する</x-button>
        </x-page-header>

        @if (session('success'))
            <x-alert variant="success">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->has('is_active'))
            <x-alert variant="error">{{ $errors->first('is_active') }}</x-alert>
        @endif

        <p class="text-sm font-semibold text-on-surface-variant">店舗：<span class="text-on-surface">{{ $shop->name }}</span></p>

        @if ($seatTypes->isEmpty())
            <x-card class="text-sm text-on-surface-variant">座席種別はまだありません。</x-card>
        @else
            <x-owner.table>
                <thead class="bg-surface-container-low text-xs font-semibold text-on-surface-variant">
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
                <tbody class="divide-y divide-outline-variant/40">
                    @foreach ($seatTypes as $seatType)
                        <tr>
                            <td class="min-w-32 px-4 py-3 font-bold text-on-surface">{{ $seatType->name }}</td>
                            <td class="min-w-48 px-4 py-3 text-xs">{{ $seatType->description }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $seatType->default_capacity }}席</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ number_format($seatType->default_price) }}円</td>
                            <td class="whitespace-nowrap px-4 py-3"><x-badge :variant="$seatType->is_active ? 'success' : 'neutral'">{{ $seatType->is_active ? '有効' : '無効' }}</x-badge></td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $seatType->broadcast_seat_types_count }}件</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <a href="{{ route('owner.seat-types.edit', $seatType) }}" class="text-sm font-bold text-primary hover:text-secondary">編集</a>
                                @if ($seatType->is_active)
                                    <form method="POST" action="{{ route('owner.seat-types.active', $seatType) }}" data-confirm="「{{ $seatType->name }}」を無効にしますか？どの放映でも、この座席の新しい予約ができなくなります（すでに入っている予約はそのまま有効です）。" class="ml-2 inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="0">
                                        <button type="submit" class="text-sm font-bold text-error hover:text-on-error-container">無効にする</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('owner.seat-types.active', $seatType) }}" class="ml-2 inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="1">
                                        <button type="submit" class="text-sm font-bold text-primary hover:text-secondary">有効にする</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-owner.table>
        @endif
    @endif
</div>
@endsection
