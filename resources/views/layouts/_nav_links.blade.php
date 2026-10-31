{{-- ヘッダーのナビ項目（パソコン幅・スマホ幅で共通）。$linkClass で見た目だけを変える --}}
<a href="{{ route('select.teams') }}" class="{{ $linkClass }}">お気に入り登録</a>
@if ($hasFavoriteTeams)
    @if ($showFavoriteOnly)
        <a href="{{ route('matches.index', ['favorite' => 0]) }}" class="{{ $linkClass }}">試合一覧</a>
    @else
        <a href="{{ route('matches.index', ['favorite' => 1]) }}" class="{{ $linkClass }}">お気に入りの試合に戻る</a>
    @endif
@endif
<a href="{{ route('mypage') }}" class="{{ $linkClass }}">マイページ</a>
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="{{ $linkClass }}">ログアウト</button>
</form>
