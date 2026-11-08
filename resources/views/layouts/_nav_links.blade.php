{{-- ヘッダーのナビ項目（パソコン幅・スマホ幅で共通）。$baseClass・$idleClass・$activeClass で見た目だけを変える。今いる画面の項目は強調し、aria-current="page" を付ける。試合一覧の切り替えリンクだけは、押すと別の表示に移るため、見た目の強調のみで aria-current は付けない --}}
@php
    $linkClass = fn (bool $active) => $baseClass.' '.($active ? $activeClass : $idleClass);
@endphp
<a href="{{ route('select.teams') }}" class="{{ $linkClass(request()->routeIs('select.teams')) }}" @if (request()->routeIs('select.teams')) aria-current="page" @endif>お気に入り登録</a>
@if ($hasFavoriteTeams)
    @if ($showFavoriteOnly)
        <a href="{{ route('matches.index', ['favorite' => 0]) }}" class="{{ $linkClass(request()->routeIs('matches.index')) }}">試合一覧</a>
    @else
        <a href="{{ route('matches.index', ['favorite' => 1]) }}" class="{{ $linkClass(request()->routeIs('matches.index')) }}">お気に入りの試合に戻る</a>
    @endif
@endif
@can('access-owner-area')
    <a href="{{ route('owner.shops.index') }}" class="{{ $linkClass(request()->routeIs('owner.shops.*')) }}" @if (request()->routeIs('owner.shops.*')) aria-current="page" @endif>店舗情報</a>
    <a href="{{ route('owner.reservations.index') }}" class="{{ $linkClass(request()->routeIs('owner.reservations.*')) }}" @if (request()->routeIs('owner.reservations.*')) aria-current="page" @endif>予約管理</a>
    <a href="{{ route('owner.broadcasts.index') }}" class="{{ $linkClass(request()->routeIs('owner.broadcasts.*')) }}" @if (request()->routeIs('owner.broadcasts.*')) aria-current="page" @endif>放映管理</a>
    <a href="{{ route('owner.seat-types.index') }}" class="{{ $linkClass(request()->routeIs('owner.seat-types.*')) }}" @if (request()->routeIs('owner.seat-types.*')) aria-current="page" @endif>座席管理</a>
@endcan
<a href="{{ route('mypage') }}" class="{{ $linkClass(request()->routeIs('mypage')) }}" @if (request()->routeIs('mypage')) aria-current="page" @endif>マイページ</a>
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="{{ $linkClass(false) }}">ログアウト</button>
</form>
