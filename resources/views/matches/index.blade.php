@extends('layouts.app')
@section('title', '試合一覧')
@section('content')
@php
    // 濃い緑の絞り込みの欄で使う、ラベルと入力欄の見た目
    $barLabelClass = 'flex min-w-0 flex-1 flex-col gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary-fixed';
    $barInputClass = 'w-full min-w-0 rounded-lg border-0 bg-surface-container-lowest px-3 py-2.5 text-sm font-medium normal-case tracking-normal text-on-surface focus:outline-none focus:ring-2 focus:ring-secondary-container';
    $emptyClass = 'rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 text-center text-sm text-on-surface-variant';
@endphp
<x-page-header eyebrow="Fixtures" title="試合一覧" description="試合の日程は日本時間（JST）で表示しています。" />

@if (session('success'))
    <x-alert variant="success" class="mb-6">{{ session('success') }}</x-alert>
@endif

@if ($searchErrors->any())
    <x-alert variant="error" class="mb-6" :messages="$searchErrors->all()" />
@endif

<form method="GET" action="{{ route('matches.index') }}" role="search"
    class="mb-8 flex flex-col gap-4 rounded-xl bg-primary-container p-4 shadow-md sm:p-5 lg:flex-row lg:items-end">
    <label class="{{ $barLabelClass }}">
        日付（日本時間）
        <input type="date" name="date" value="{{ $searchParams['date'] }}" class="{{ $barInputClass }}">
    </label>
    <label class="{{ $barLabelClass }}">
        チーム名
        <input type="text" name="team" value="{{ $searchParams['team'] }}" maxlength="50" placeholder="例：Arsenal、ARS" class="{{ $barInputClass }}">
    </label>
    <label class="{{ $barLabelClass }}">
        大会（検索用）
        <select name="competition" class="{{ $barInputClass }}">
            <option value="">すべての大会</option>
            @foreach ($competitions as $competitionOption)
                <option value="{{ $competitionOption->code }}" @selected(($isSearching || $searchErrors->any()) && $competitionOption->code === $searchParams['competition'])>
                    {{ $competitionOption->displayName() }}
                </option>
            @endforeach
        </select>
    </label>
    <div class="flex items-center gap-4">
        <button type="submit" class="rounded-lg bg-secondary-container px-6 py-2.5 text-sm font-bold text-on-secondary-fixed transition hover:bg-secondary-fixed focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-fixed">検索</button>
        <a href="{{ route('matches.index') }}" class="text-sm font-semibold text-primary-fixed underline">クリア</a>
    </div>
</form>

@if ($isSearching)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-headline text-lg font-semibold text-primary">検索結果（{{ $searchResults->total() }}件）</h2>
        <a href="{{ route('matches.index') }}" class="text-sm font-semibold text-secondary underline">検索をクリア</a>
    </div>

    @if ($showPeriodTabs)
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach (['upcoming' => '今後の試合', 'past' => '過去の試合'] as $periodKey => $periodLabel)
                {{-- 押すと別の表示に移る切り替えなので aria-current は付けない --}}
                <x-match.tab :href="route('matches.index', ['team' => $searchParams['team'], 'competition' => $searchParams['competition'], 'period' => $periodKey])" :active="$searchParams['period'] === $periodKey">{{ $periodLabel }}</x-match.tab>
            @endforeach
        </div>
    @endif

    @if ($searchResults->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($searchResults as $footballMatch)
                @include('matches._card', ['footballMatch' => $footballMatch])
            @endforeach
        </div>
        <div class="mt-6">
            {{ $searchResults->links() }}
        </div>
    @else
        <p class="{{ $emptyClass }} py-10">
            条件に合う試合はありません。
        </p>
    @endif
@elseif ($showFavoriteOnly)
    <h2 class="mb-3 font-headline text-lg font-semibold text-primary">お気に入りチーム</h2>
    <div class="mb-8 flex flex-wrap gap-3">
        @foreach ($favoriteTeams as $favoriteTeam)
            <span class="flex items-center gap-2 rounded-full bg-primary-container px-4 py-2 text-sm font-bold text-on-primary">
                @if ($favoriteTeam->crest_url)
                    <img src="{{ $favoriteTeam->crest_url }}" alt="" class="h-5 w-5 object-contain">
                @endif
                {{ $favoriteTeam->displayShortName() }}
            </span>
        @endforeach
    </div>

    @foreach ($favoriteTeamMatches as $group)
        <section class="mb-10 border-b border-outline-variant/50 pb-8 last:mb-0 last:border-b-0 last:pb-0">
            <h3 class="mb-4 flex items-center gap-3 font-headline text-xl font-semibold text-primary">
                @if ($group['team']->crest_url)
                    <img src="{{ $group['team']->crest_url }}" alt="" class="h-8 w-8 object-contain">
                @endif
                {{ $group['team']->displayShortName() }}
            </h3>

            <x-match.section-label class="mb-3">消化試合</x-match.section-label>
            @if ($group['recentMatches']->isNotEmpty())
                <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group['recentMatches'] as $footballMatch)
                        @include('matches._card', ['footballMatch' => $footballMatch])
                    @endforeach
                </div>
            @else
                <p class="{{ $emptyClass }} mb-6 py-6">
                    該当試合はありません。
                </p>
            @endif

            <x-match.section-label class="mb-3">これからの試合</x-match.section-label>
            @if ($group['upcomingMatches']->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group['upcomingMatches'] as $footballMatch)
                        @include('matches._card', ['footballMatch' => $footballMatch])
                    @endforeach
                </div>
            @else
                <p class="{{ $emptyClass }} py-6">
                    該当試合はありません。
                </p>
            @endif
        </section>
    @endforeach
@else
    <form method="GET" action="{{ route('matches.index') }}"
        class="mb-8 flex flex-col gap-4 rounded-xl bg-primary-container p-4 shadow-md sm:flex-row sm:items-end sm:gap-6 sm:p-5">
        <label class="{{ $barLabelClass }}">
            大会選択
            <select name="competition" id="competition_id" onchange="this.form.submit()" class="{{ $barInputClass }}">
                @foreach ($competitions as $competitionOption)
                    <option value="{{ $competitionOption->code }}" @selected($competitionOption->code === $competitionCode)>
                        {{ $competitionOption->displayName() }}
                    </option>
                @endforeach
            </select>
        </label>

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
        @endphp
        @if ($isRegularSeasonOnly)
            <label class="{{ $barLabelClass }}">
                何節か選択
                <select name="match_day" id="matchDay" onchange="this.form.submit()" class="{{ $barInputClass }}">
                    @foreach ($matchDays as $matchDayOption)
                        <option value="{{ $matchDayOption }}" @selected((int) $matchDayOption === $matchDay)>
                            第{{ $matchDayOption }}節
                        </option>
                    @endforeach
                </select>
            </label>
        @else
            <label class="{{ $barLabelClass }}">
                ステージ選択
                <select name="stage" id="stage" onchange="this.form.submit()" class="{{ $barInputClass }}">
                    @foreach ($stages as $stageOption)
                        <option value="{{ $stageOption }}" @selected($stageOption === $stage)>
                            {{ $stageLabels[$stageOption] ?? $stageOption }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif
    </form>

    @if ($footballMatches->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($footballMatches as $footballMatch)
                @include('matches._card', ['footballMatch' => $footballMatch])
            @endforeach
        </div>
    @else
        <p class="{{ $emptyClass }} py-10">
            該当する試合が見つかりませんでした。
        </p>
    @endif
@endif
@endsection
