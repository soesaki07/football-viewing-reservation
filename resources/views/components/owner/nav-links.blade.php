{{-- 店舗オーナー用ヘッダーのナビ項目（パソコン幅・スマホ幅で共通）。$baseClass・$idleClass・$activeClass で見た目だけを変える。今いる画面の項目は強調し、aria-current="page" を付ける --}}
@props(['baseClass', 'idleClass', 'activeClass'])
@php
    $items = [
        ['label' => '店舗情報', 'url' => route('owner.shops.index'), 'active' => request()->routeIs('owner.shops.*')],
        ['label' => '予約管理', 'url' => route('owner.reservations.index'), 'active' => request()->routeIs('owner.reservations.*')],
        ['label' => '放映管理', 'url' => route('owner.broadcasts.index'), 'active' => request()->routeIs('owner.broadcasts.*')],
        ['label' => '座席管理', 'url' => route('owner.seat-types.index'), 'active' => request()->routeIs('owner.seat-types.*')],
        ['label' => 'マイページ', 'url' => route('mypage'), 'active' => request()->routeIs('mypage')],
    ];
@endphp
@foreach ($items as $item)
    <a href="{{ $item['url'] }}" class="{{ $baseClass }} {{ $item['active'] ? $activeClass : $idleClass }}" @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
@endforeach
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="{{ $baseClass }} {{ $idleClass }}">ログアウト</button>
</form>
