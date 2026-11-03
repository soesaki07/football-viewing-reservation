@extends('layouts.app')
@section('title', '店舗を登録')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">🍺</span>
        店舗を登録
    </h1>

    <form method="POST" action="{{ route('owner.shops.store') }}" class="space-y-6 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
        @csrf

        @include('owner.shops._form')

        <p class="text-xs text-pitch-500">登録した店舗は非公開になります。座席種別と放映を登録してから、店舗情報の編集画面で公開してください。</p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-pitch-800 px-6 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">登録する</button>
            <a href="{{ route('owner.shops.index') }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">一覧に戻る</a>
        </div>
    </form>
</div>
@endsection
