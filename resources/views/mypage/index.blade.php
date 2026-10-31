@extends('layouts.app')
@section('title', 'マイページ')
@section('content')
<div class="mx-auto max-w-3xl space-y-10">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">👤</span>
        マイページ
    </h1>

    <section>
        <h2 class="mb-3 text-lg font-bold text-pitch-950">ユーザー情報</h2>
        <dl class="grid gap-3 rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700 shadow-sm sm:grid-cols-[8rem_1fr]">
            <dt class="font-semibold text-pitch-500">名前</dt>
            <dd>{{ $user->name }}</dd>
            <dt class="font-semibold text-pitch-500">メールアドレス</dt>
            <dd>{{ $user->email }}</dd>
        </dl>
    </section>

    <section>
        <h2 class="mb-3 text-lg font-bold text-pitch-950">今後の予約</h2>
        @include('mypage._reservation_list', ['reservations' => $upcomingReservations, 'emptyMessage' => '今後の予約はありません。'])
    </section>

    <section>
        <h2 class="mb-3 text-lg font-bold text-pitch-950">過去の予約</h2>
        @include('mypage._reservation_list', ['reservations' => $pastReservations, 'emptyMessage' => '過去の予約はありません。'])
    </section>

    <section>
        <h2 class="mb-3 text-lg font-bold text-pitch-950">お気に入りチーム</h2>
        <div class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700 shadow-sm">
            @if ($favoriteTeams->isEmpty())
                <p>お気に入りチームはまだ登録されていません。</p>
            @else
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($favoriteTeams as $team)
                        <li>{{ $team->name }}</li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('select.teams') }}" class="mt-4 inline-block font-bold text-pitch-700 underline transition hover:text-gold-500">お気に入り登録</a>
        </div>
    </section>
</div>
@endsection
