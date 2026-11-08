@extends('layouts.owner')
@section('title', '店舗を登録')
@section('content')
<div class="space-y-6">
    <nav aria-label="パンくず" class="text-xs font-semibold text-on-surface-variant">
        <a href="{{ route('owner.shops.index') }}" class="hover:text-primary">店舗情報</a>
        <span aria-hidden="true">/</span>
        <span class="text-on-surface">店舗を登録</span>
    </nav>

    <x-page-header title="店舗を登録" eyebrow="Partner Console" />

    <form method="POST" action="{{ route('owner.shops.store') }}">
        @csrf

        <x-card class="space-y-8">
            @include('owner.shops._form')

            <p class="text-xs text-on-surface-variant">登録した店舗は非公開になります。座席種別と放映を登録してから、店舗情報の編集画面で公開してください。</p>

            <div class="flex flex-wrap items-center gap-3">
                <x-button>登録する</x-button>
                <x-button variant="secondary" :href="route('owner.shops.index')">一覧に戻る</x-button>
            </div>
        </x-card>
    </form>
</div>
@endsection
