@extends('layouts.app')
@section('title', '試合一覧')
@section('content')
<h1 class="mb-6 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
    <span class="text-gold-500">⚽</span>
    試合一覧
</h1>

<form method="GET" action="{{ route('matches.index') }}"
      class="mb-8 flex flex-col gap-4 rounded-xl bg-pitch-950 p-4 shadow-md sm:flex-row sm:items-end sm:gap-6 sm:p-5">
    <label class="flex flex-1 flex-col gap-1 text-sm font-semibold text-pitch-100">
        大会選択
        <select name="competition" id="competition_id" onchange="this.form.submit()"
                class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
            @foreach ($competitions as $competitionOption)
                <option value="{{ $competitionOption->code }}" @selected($competitionOption->code === $competitionCode)>
                    {{ $competitionOption->name }}
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

@php
    $statusLabels = [
        'SCHEDULED' => ['label' => '開催前', 'class' => 'bg-pitch-100 text-pitch-700'],
        'TIMED' => ['label' => '開催前', 'class' => 'bg-pitch-100 text-pitch-700'],
        'IN_PLAY' => ['label' => 'LIVE', 'class' => 'animate-pulse bg-red-100 text-red-700'],
        'PAUSED' => ['label' => 'LIVE（中断中）', 'class' => 'bg-red-100 text-red-700'],
        'FINISHED' => ['label' => '終了', 'class' => 'bg-pitch-950 text-gold-400'],
        'POSTPONED' => ['label' => '延期', 'class' => 'bg-yellow-100 text-yellow-800'],
        'SUSPENDED' => ['label' => '中断', 'class' => 'bg-yellow-100 text-yellow-800'],
        'CANCELLED' => ['label' => '中止', 'class' => 'bg-gray-200 text-gray-600'],
    ];
@endphp

@if ($footballMatches->isNotEmpty())
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($footballMatches as $footballMatch)
            @php
                $statusInfo = $statusLabels[$footballMatch->status] ?? ['label' => $footballMatch->status, 'class' => 'bg-pitch-100 text-pitch-700'];
            @endphp
            <a href="{{ route('matches.show', $footballMatch) }}" class="group relative overflow-hidden rounded-xl border border-pitch-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-pitch-600 to-gold-500"></span>

                <p class="mb-3 flex items-center justify-between gap-3 text-base font-bold text-pitch-950 sm:text-lg">
                    <span class="flex min-w-0 items-center gap-2">
                        @if ($footballMatch->homeTeam->crest_url)
                            <img src="{{ $footballMatch->homeTeam->crest_url }}" alt="" class="h-6 w-6 shrink-0 object-contain">
                        @endif
                        <span class="truncate">{{ $footballMatch->homeTeam->tla ?? $footballMatch->homeTeam->short_name ?? $footballMatch->homeTeam->name }}</span>
                    </span>

                    @if (! is_null($footballMatch->home_score) && ! is_null($footballMatch->away_score))
                        <span class="shrink-0 rounded-full bg-pitch-950 px-3 py-0.5 text-sm font-extrabold text-gold-400">
                            {{ $footballMatch->home_score }} - {{ $footballMatch->away_score }}
                        </span>
                    @else
                        <span class="shrink-0 rounded-full bg-pitch-950 px-2 py-0.5 text-xs font-semibold text-gold-400">VS</span>
                    @endif

                    <span class="flex min-w-0 items-center justify-end gap-2">
                        <span class="truncate text-right">{{ $footballMatch->awayTeam->tla ?? $footballMatch->awayTeam->short_name ?? $footballMatch->awayTeam->name }}</span>
                        @if ($footballMatch->awayTeam->crest_url)
                            <img src="{{ $footballMatch->awayTeam->crest_url }}" alt="" class="h-6 w-6 shrink-0 object-contain">
                        @endif
                    </span>
                </p>

                <p class="flex items-center justify-between gap-2 text-sm font-medium text-pitch-700">
                    <span>
                        {{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}
                        <span class="text-pitch-500">(JST)</span>
                    </span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold {{ $statusInfo['class'] }}">
                        {{ $statusInfo['label'] }}
                    </span>
                </p>
            </a>
        @endforeach
    </div>
@else
    <p class="rounded-lg border border-dashed border-pitch-100 bg-white px-6 py-10 text-center text-sm text-pitch-700">
        該当する試合が見つかりませんでした。
    </p>
@endif
@endsection
