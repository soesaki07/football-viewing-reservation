@extends('layouts.app')
@section('title', '試合詳細')
@section('content')
@php
    $stageLabels = [
        'LEAGUE_STAGE' => 'リーグステージ',
        'GROUP_STAGE' => 'グループステージ',
        'LAST_32' => 'ベスト32',
        'LAST_16' => 'ベスト16',
        'QUARTER_FINALS' => 'ベスト8',
        'SEMI_FINALS' => '準決勝',
        'THIRD_PLACE' => '3位決定戦',
        'FINAL' => '決勝',
        'PLAY_OFFS' => 'プレイオフ',
    ];
    $stageLabel = $stageLabels[$footballMatch->stage] ?? $footballMatch->stage;
@endphp
<h1 class="sr-only">試合詳細</h1>
<div class="mx-auto max-w-4xl">
    <a href="{{ route('matches.index', ['competition' => $footballMatch->competition->code, 'match_day' => $footballMatch->match_day, 'stage' => $footballMatch->stage]) }}" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-on-surface-variant transition hover:text-primary">
        <span aria-hidden="true">←</span> 試合一覧に戻る
    </a>

    {{-- 試合の見出し（大会・ステージ／節・状態・両チーム・キックオフ） --}}
    <div class="overflow-hidden rounded-xl bg-primary-container p-5 text-on-primary shadow-md sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-center gap-2">
            @if ($footballMatch->competition->emblem_url)
                <img src="{{ $footballMatch->competition->emblem_url }}" alt="" class="mr-1 h-10 w-10 shrink-0 rounded bg-surface-container-lowest object-contain p-1">
            @endif
            <span class="rounded-full bg-primary px-3 py-1 text-xs font-bold text-primary-fixed">{{ $footballMatch->competition->displayName() }}</span>
            @if ($footballMatch->stage === 'REGULAR_SEASON')
                <span class="rounded-full bg-secondary-container px-3 py-1 text-xs font-bold text-on-secondary-container">第{{ $footballMatch->match_day }}節</span>
            @else
                <span class="rounded-full bg-secondary-container px-3 py-1 text-xs font-bold text-on-secondary-container">{{ $stageLabel }}</span>
            @endif
            <x-match.status-badge :match="$footballMatch" />
        </div>

        <div class="flex items-center justify-center gap-3 sm:gap-8">
            <div class="flex min-w-0 flex-1 flex-col items-center gap-3 text-center">
                @if ($footballMatch->homeTeam->crest_url)
                    <img src="{{ $footballMatch->homeTeam->crest_url }}" alt="" class="h-16 w-16 object-contain sm:h-24 sm:w-24">
                @endif
                <p class="max-w-full break-words font-headline text-base font-semibold sm:text-2xl">{{ $footballMatch->homeTeam->displayName() }}</p>
            </div>

            @if (! is_null($footballMatch->home_score) && ! is_null($footballMatch->away_score))
                <span data-testid="match-score" class="shrink-0 rounded-xl bg-primary px-4 py-2 text-2xl font-extrabold text-secondary-container sm:px-6 sm:text-4xl">
                    {{ $footballMatch->home_score }} - {{ $footballMatch->away_score }}
                </span>
            @else
                <span data-testid="match-vs" class="shrink-0 rounded-xl bg-primary px-3 py-1.5 text-sm font-bold text-secondary-container sm:text-lg">VS</span>
            @endif

            <div class="flex min-w-0 flex-1 flex-col items-center gap-3 text-center">
                @if ($footballMatch->awayTeam->crest_url)
                    <img src="{{ $footballMatch->awayTeam->crest_url }}" alt="" class="h-16 w-16 object-contain sm:h-24 sm:w-24">
                @endif
                <p class="max-w-full break-words font-headline text-base font-semibold sm:text-2xl">{{ $footballMatch->awayTeam->displayName() }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-col items-center gap-1 border-t border-on-primary-container/30 pt-5 text-sm">
            <p>
                <span class="text-[11px] font-bold uppercase tracking-widest text-secondary-container">キックオフ時間（日本時間）</span>
            </p>
            <p class="font-headline text-lg font-semibold">{{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</p>
            @if (isset($footballMatch->venue))
                <p class="text-on-primary-container">
                    <span class="font-semibold">会場</span>
                    {{ $footballMatch->venue }}
                </p>
            @endif
        </div>
    </div>

    <section class="mt-10">
        <h2 class="mb-4 font-headline text-xl font-semibold text-primary sm:text-2xl">放映店舗</h2>
        @if (session('error'))
            <x-alert variant="error" class="mb-4">{{ session('error') }}</x-alert>
        @endif
        @if ($isFinished)
            <x-card class="text-center text-sm text-on-surface-variant">この試合の放映は終了しました。</x-card>
        @elseif ($broadcastShops->isEmpty())
            <x-card class="text-center text-sm text-on-surface-variant">放映店舗がありません。</x-card>
        @else
            @php
                $sortLabels = ['seats' => '空席の多い順', 'price' => '料金の安い順', 'name' => '店舗名順'];
            @endphp
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-[11px] font-bold uppercase tracking-widest text-secondary">並び順</span>
                @foreach ($sortLabels as $sortKey => $sortLabel)
                    <x-match.tab :href="request()->fullUrlWithQuery(['sort' => $sortKey])" :active="$sort === $sortKey" :aria-current="$sort === $sortKey ? 'true' : null">{{ $sortLabel }}</x-match.tab>
                @endforeach
            </div>
            <ul class="flex flex-col gap-3">
                @foreach ($broadcastShops as $row)
                    <li data-testid="broadcast-shop" class="flex flex-col gap-3 rounded-xl bg-surface-container-lowest p-4 shadow-sm ring-1 ring-outline-variant/40 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0">
                            <a href="{{ route('shops.show', $row['shop']) }}" class="break-words font-headline text-lg font-semibold text-primary underline-offset-4 transition hover:underline">{{ $row['shop']->name }}</a>
                            <p class="text-sm text-on-surface-variant">{{ $row['shop']->prefecture }}{{ $row['shop']->city }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-on-surface-variant">
                            <span class="font-bold text-on-surface">{{ number_format($row['minPrice']) }}円〜</span>
                            <span>
                                @if ($row['seats'] > 0)
                                    残り{{ $row['seats'] }}席
                                @else
                                    <x-badge variant="danger">満席</x-badge>
                                @endif
                            </span>
                            @if ($row['reservationStatus'] === 'before')
                                <x-badge variant="warning">受付前</x-badge>
                            @elseif ($row['reservationStatus'] === 'closed')
                                <x-badge variant="neutral">受付終了</x-badge>
                            @endif
                            @if ($row['reservationId'])
                                <span class="inline-flex items-center rounded-full bg-secondary-container px-2.5 py-0.5 text-xs font-bold text-on-secondary-container">予約済み</span>
                                <a href="{{ route('reservations.show', $row['reservationId']) }}" class="text-xs font-bold text-primary underline transition hover:text-secondary">予約内容を見る</a>
                            @elseif ($row['canReserve'])
                                <x-button size="sm" :href="route('reservations.create', $row['broadcastId'])">予約する</x-button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
