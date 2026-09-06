@extends('layouts.app')
@section('title', '試合詳細')
@section('content')
<h1 class="mb-6 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
    <span class="text-gold-500">⚽</span>
    試合詳細
</h1>
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
    $stageLabels = [
        'LEAGUE_STAGE' => ['label' => 'リーグステージ', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'GROUP_STAGE' => ['label' => 'グループステージ', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'LAST_32' => ['label' => 'ベスト32', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'LAST_16' => ['label' => 'ベスト16', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'QUARTER_FINALS' => ['label' => 'ベスト8', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'SEMI_FINALS' => ['label' => '準決勝', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'THIRD_PLACE' => ['label' => '3位決定戦', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'FINAL' => ['label' => '決勝', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
        'PLAY_OFFS' => ['label' => 'プレイオフ', 'class' => 'inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700'],
    ];
@endphp
<div class="mx-auto max-w-3xl">
    @php
        $statusInfo = $statusLabels[$footballMatch->status] ?? ['label' => $footballMatch->status, 'class' => 'bg-pitch-100 text-pitch-700'];
        $stageInfo = $stageLabels[$footballMatch->stage] ?? ['label' => $footballMatch->stage, 'class' => 'bg-pitch-100 text-pitch-700'];
    @endphp
    <div class="relative mb-3 flex justify-center">
        <a href="{{ route('matches.index', ['competition' => $footballMatch->competition->code, 'match_day' => $footballMatch->match_day, 'stage' => $footballMatch->stage]) }}" class="absolute left-0 top-1/2 inline-flex -translate-y-1/2 items-center gap-1 text-sm font-semibold text-pitch-700 transition hover:text-gold-500">
            ← 一覧に戻る
        </a>
        <span class="flex flex-col items-center gap-0">
            <img src="{{ $footballMatch->competition->emblem_url }}" class="h-28 w-28 object-contain">
        </span>
    </div>
    <div class="flex flex-col items-center gap-5 overflow-hidden rounded-xl border border-pitch-100 bg-white p-8 text-center shadow-sm">
        @if ($footballMatch->stage === 'REGULAR_SEASON')
            <span class="inline-block rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold tracking-wide text-pitch-700">第{{ $footballMatch->match_day }}節</span>
        @else
            <span class="{{ $stageInfo['class'] }}">{{ $stageInfo['label'] }}</span>
        @endif
        <span class="flex w-full items-center justify-center gap-4 sm:gap-6">
            <span class="flex flex-1 flex-col items-center gap-3">
                @if ($footballMatch->homeTeam->crest_url)
                    <img src="{{ $footballMatch->homeTeam->crest_url }}" alt="" class="h-20 w-20 object-contain">
                @endif
                <span class="text-lg font-bold text-pitch-950 sm:text-xl">{{ $footballMatch->homeTeam->name }}</span>
            </span>
            @if (! is_null($footballMatch->home_score) && ! is_null($footballMatch->away_score))
                <span class="shrink-0 rounded-full bg-pitch-950 px-6 py-2 text-3xl font-extrabold text-gold-400 sm:text-4xl">
                    {{ $footballMatch->home_score }} - {{ $footballMatch->away_score }}
                </span>
            @else
                <span class="shrink-0 rounded-full bg-pitch-950 px-3 py-1 text-sm font-semibold text-gold-400">VS</span>
            @endif

            <span class="flex min-w-0 flex-1 flex-col items-center justify-end gap-3">
                @if ($footballMatch->awayTeam->crest_url)
                    <img src="{{ $footballMatch->awayTeam->crest_url }}" alt="" class="h-20 w-20 object-contain">
                @endif
                <span class="truncate text-right text-lg font-bold text-pitch-950 sm:text-xl">{{ $footballMatch->awayTeam->name }}</span>
            </span>
        </span>
        <span class="flex w-full flex-col items-center gap-2 border-t border-pitch-100 pt-5 text-sm text-pitch-700">
            <span>
                <span class="font-semibold text-pitch-500">キックオフ時間（日本時間）</span>
                {{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}
            </span>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusInfo['class'] }}">
                {{ $statusInfo['label'] }}
            </span>
            @if (isset($footballMatch->venue))
                <span>
                    <span class="font-semibold text-pitch-500">会場</span>
                    {{ $footballMatch->venue }}
                </span>
            @endif
        </span>
    </div>
</div>

@endsection
