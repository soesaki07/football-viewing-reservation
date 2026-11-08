@extends('layouts.app')
@section('title', 'マイページ')
@section('content')
<div class="mx-auto max-w-3xl">
    <x-page-header title="マイページ" />
</div>
<div class="mx-auto max-w-3xl space-y-10">

    <section>
        <h2 class="mb-3 font-headline text-lg font-semibold text-primary">ユーザー情報</h2>
        <x-card>
            <dl class="grid gap-3 text-sm sm:grid-cols-[8rem_1fr]">
                <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">名前</dt>
                <dd class="break-words text-on-surface">{{ $user->name }}</dd>
                <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">メールアドレス</dt>
                <dd class="break-all text-on-surface">{{ $user->email }}</dd>
            </dl>
        </x-card>
    </section>

    <section>
        <h2 class="mb-3 font-headline text-lg font-semibold text-primary">今後の予約</h2>
        @include('mypage._reservation_list', ['reservations' => $upcomingReservations, 'emptyMessage' => '今後の予約はありません。'])
    </section>

    <section>
        <h2 class="mb-3 font-headline text-lg font-semibold text-primary">過去の予約</h2>
        @include('mypage._reservation_list', ['reservations' => $pastReservations, 'emptyMessage' => '過去の予約はありません。'])
    </section>

    <section>
        <h2 class="mb-3 font-headline text-lg font-semibold text-primary">お気に入りチーム</h2>
        <x-card class="space-y-4 text-sm text-on-surface-variant">
            @if ($favoriteTeams->isEmpty())
                <p>お気に入りチームはまだ登録されていません。</p>
            @else
                <ul class="flex flex-wrap gap-2">
                    @foreach ($favoriteTeams as $team)
                        <li><x-badge variant="neutral" class="px-3 py-1 text-sm">{{ $team->displayName() }}</x-badge></li>
                    @endforeach
                </ul>
            @endif
            <x-button variant="secondary" size="sm" :href="route('select.teams')">お気に入り登録</x-button>
        </x-card>
    </section>
</div>
@endsection
