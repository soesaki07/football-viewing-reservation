@extends('layouts.owner')
@section('title', '座席種別を編集')
@section('content')
<div class="space-y-6">
    <nav aria-label="パンくず" class="text-xs font-semibold text-on-surface-variant">
        <a href="{{ route('owner.seat-types.index', ['shop' => $shop->id]) }}" class="hover:text-primary">座席管理</a>
        <span aria-hidden="true">/</span>
        <span class="text-on-surface">座席種別を編集</span>
    </nav>

    <x-page-header title="座席種別を編集" eyebrow="Partner Console" />

    <p class="text-sm font-semibold text-on-surface-variant">店舗：<span class="text-on-surface">{{ $shop->name }}</span></p>

    @unless ($seatType->is_active)
        <x-alert variant="error" role="status">この座席種別は無効です。有効にするには、一覧の「有効にする」を押してください。</x-alert>
    @endunless

    <form method="POST" action="{{ route('owner.seat-types.update', $seatType) }}">
        @csrf
        @method('PUT')

        <x-card class="space-y-8">
            @include('owner.seat-types._form')

            <p class="text-xs text-on-surface-variant">この座席種別を使っている放映：{{ $seatType->broadcast_seat_types_count }}件</p>

            <div class="flex flex-wrap items-center gap-3">
                <x-button>更新する</x-button>
                <x-button variant="secondary" :href="route('owner.seat-types.index', ['shop' => $shop->id])">一覧に戻る</x-button>
            </div>
        </x-card>
    </form>
</div>
@endsection
