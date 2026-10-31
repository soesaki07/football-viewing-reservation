@extends('layouts.app')
@section('title', '試合一覧')
@section('content')
<h1 class="mb-6 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
    <span class="text-gold-500">⚽</span>
    試合一覧
</h1>
@if (session('success'))
    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if ($searchErrors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-inside list-disc">
            @foreach ($searchErrors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="GET" action="{{ route('matches.index') }}" role="search"
    class="mb-8 flex flex-col gap-4 rounded-xl bg-pitch-950 p-4 shadow-md lg:flex-row lg:items-end sm:p-5">
    <label class="flex flex-1 flex-col gap-1 text-sm font-semibold text-pitch-100">
        日付（日本時間）
        <input type="date" name="date" value="{{ $searchParams['date'] }}"
               class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
    </label>
    <label class="flex flex-1 flex-col gap-1 text-sm font-semibold text-pitch-100">
        チーム名
        <input type="text" name="team" value="{{ $searchParams['team'] }}" maxlength="50" placeholder="例：Arsenal、ARS"
               class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
    </label>
    <label class="flex flex-1 flex-col gap-1 text-sm font-semibold text-pitch-100">
        大会（検索用）
        <select name="competition"
                class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
            <option value="">すべての大会</option>
            @foreach ($competitions as $competitionOption)
                <option value="{{ $competitionOption->code }}" @selected($isSearching && $competitionOption->code === $searchParams['competition'])>
                    {{ $competitionOption->name }}
                </option>
            @endforeach
        </select>
    </label>
    <div class="flex items-center gap-4">
        <button type="submit" class="rounded-lg bg-gold-500 px-5 py-2 text-sm font-bold text-pitch-950 hover:bg-gold-400">検索</button>
        <a href="{{ route('matches.index') }}" class="text-sm font-semibold text-pitch-100 underline">クリア</a>
    </div>
</form>

@if ($isSearching)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-pitch-950">検索結果（{{ $searchResults->total() }}件）</h2>
        <a href="{{ route('matches.index') }}" class="text-sm font-semibold text-pitch-700 underline">検索をクリア</a>
    </div>

    @if ($showPeriodTabs)
        <div class="mb-4 flex gap-2">
            @foreach (['upcoming' => '今後の試合', 'past' => '過去の試合'] as $periodKey => $periodLabel)
                <a href="{{ route('matches.index', ['team' => $searchParams['team'], 'competition' => $searchParams['competition'], 'period' => $periodKey]) }}"
                   @if ($searchParams['period'] === $periodKey) aria-current="page" @endif
                   class="rounded-full px-4 py-2 text-sm font-bold {{ $searchParams['period'] === $periodKey ? 'bg-pitch-950 text-gold-400' : 'bg-white text-pitch-700 border border-pitch-100' }}">
                    {{ $periodLabel }}
                </a>
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
        <p class="rounded-lg border border-dashed border-pitch-100 bg-white px-6 py-10 text-center text-sm text-pitch-700">
            条件に合う試合はありません。
        </p>
    @endif
@elseif ($showFavoriteOnly)
    <h2 class="mb-3 text-lg font-bold text-pitch-950">お気に入りチーム</h2>
    <div class="mb-8 flex flex-wrap gap-3">
        @foreach ($favoriteTeams as $favoriteTeam)
            <span class="flex items-center gap-2 rounded-full bg-pitch-950 px-4 py-2 text-sm font-bold text-gold-400">
                @if ($favoriteTeam->crest_url)
                    <img src="{{ $favoriteTeam->crest_url }}" alt="" class="h-5 w-5 object-contain">
                @endif
                {{ $favoriteTeam->displayShortName() }}
            </span>
        @endforeach
    </div>

    @foreach ($favoriteTeamMatches as $group)
        <div class="mb-10 border-b border-pitch-100 pb-8 last:mb-0 last:border-b-0 last:pb-0">
            <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-pitch-950">
                @if ($group['team']->crest_url)
                    <img src="{{ $group['team']->crest_url }}" alt="" class="h-6 w-6 object-contain">
                @endif
                {{ $group['team']->displayShortName() }}
            </h3>

            <h4 class="mb-2 text-xs font-bold tracking-wide text-pitch-500 uppercase">消化試合</h4>
            @if ($group['recentMatches']->isNotEmpty())
                <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group['recentMatches'] as $footballMatch)
                        @include('matches._card', ['footballMatch' => $footballMatch])
                    @endforeach
                </div>
            @else
                <p class="mb-6 rounded-lg border border-dashed border-pitch-100 bg-white px-6 py-6 text-center text-sm text-pitch-700">
                    該当試合はありません。
                </p>
            @endif

            <h4 class="mb-2 text-xs font-bold tracking-wide text-pitch-500 uppercase">これからの試合</h4>
            @if ($group['upcomingMatches']->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group['upcomingMatches'] as $footballMatch)
                        @include('matches._card', ['footballMatch' => $footballMatch])
                    @endforeach
                </div>
            @else
                <p class="rounded-lg border border-dashed border-pitch-100 bg-white px-6 py-6 text-center text-sm text-pitch-700">
                    該当試合はありません。
                </p>
            @endif
        </div>
    @endforeach
@else
    <form method="GET" action="{{ route('matches.index') }}"
        class="mb-8 flex flex-col gap-4 rounded-xl bg-pitch-950 p-4 shadow-md sm:flex-row sm:items-end sm:gap-6 sm:p-5">
        <label class="flex flex-1 flex-col gap-1 text-sm font-semibold text-pitch-100">
            大会選択
            <select name="competition" id="competition_id" onchange="this.form.submit()"
                    class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
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
            <label class="flex flex-1 flex-col gap-1 text-sm font-semibold text-pitch-100">
                何節か選択
                <select name="match_day" id="matchDay" onchange="this.form.submit()"
                        class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
                    @foreach ($matchDays as $matchDayOption)
                        <option value="{{ $matchDayOption }}" @selected((int) $matchDayOption === $matchDay)>
                            第{{ $matchDayOption }}節
                        </option>
                    @endforeach
                </select>
            </label>
        @else
            <label class="flex flex-1 flex-col gap-1 text-sm font-semibold text-pitch-100">
                ステージ選択
                <select name="stage" id="stage" onchange="this.form.submit()"
                        class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
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
        <p class="rounded-lg border border-dashed border-pitch-100 bg-white px-6 py-10 text-center text-sm text-pitch-700">
            該当する試合が見つかりませんでした。
        </p>
    @endif
@endif
@endsection
