@extends('layouts.owner')
@section('title', '座席種別を登録')
@if (! $shops->isEmpty())
@section('shop-switcher')
<x-owner.shop-switcher :shops="$shops" :current="$shop" />
@endsection
@endif
@section('content')
<div class="space-y-6">
    @if ($shops->isEmpty())
        <x-page-header title="座席種別を登録" eyebrow="Partner Console" />
        <x-card class="text-sm text-on-surface-variant">店舗が登録されていません。</x-card>
    @else
        <nav aria-label="パンくず" class="text-xs font-semibold text-on-surface-variant">
            <a href="{{ route('owner.seat-types.index', ['shop' => $shop->id]) }}" class="hover:text-primary">座席管理</a>
            <span aria-hidden="true">/</span>
            <span class="text-on-surface">座席種別を登録</span>
        </nav>

        <x-page-header title="座席種別を登録" eyebrow="Partner Console" />

        <p class="text-sm font-semibold text-on-surface-variant">店舗：<span class="text-on-surface">{{ $shop->name }}</span></p>

        <form method="POST" action="{{ route('owner.seat-types.store') }}">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $shop->id }}">

            <x-card class="space-y-8">
                @include('owner.seat-types._form')

                <div class="flex flex-wrap items-center gap-3">
                    <x-button>登録する</x-button>
                    <x-button variant="secondary" :href="route('owner.seat-types.index', ['shop' => $shop->id])">一覧に戻る</x-button>
                </div>
            </x-card>
        </form>
    @endif
</div>
@endsection
