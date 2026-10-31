@extends('layouts.app')
@section('title', $shop->name)
@section('content')
<h1 class="mb-6 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
    <span class="text-gold-500">🍺</span>
    {{ $shop->name }}
</h1>
<div class="mx-auto flex max-w-3xl flex-col gap-6">
    {{-- 店舗情報 --}}
    <section class="rounded-xl border border-pitch-100 bg-white p-6 shadow-sm">
        @if ($shop->description)
            <p class="mb-5 whitespace-pre-line text-pitch-700">{{ $shop->description }}</p>
        @endif
        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm">
            <dt class="font-semibold text-pitch-500">住所</dt>
            <dd class="text-pitch-950">
                @if ($shop->postal_code)
                    〒{{ $shop->postal_code }}<br>
                @endif
                {{ $shop->fullAddress() }}
            </dd>
            @if ($shop->phone_number)
                <dt class="font-semibold text-pitch-500">電話番号</dt>
                <dd class="text-pitch-950">{{ $shop->phone_number }}</dd>
            @endif
            @if ($shop->businessHoursLabel())
                <dt class="font-semibold text-pitch-500">営業時間</dt>
                <dd class="text-pitch-950">{{ $shop->businessHoursLabel() }}</dd>
            @endif
            @if ($shop->hasSafeWebsiteUrl())
                <dt class="font-semibold text-pitch-500">Webサイト</dt>
                <dd class="min-w-0">
                    <a href="{{ $shop->website_url }}" target="_blank" rel="noopener noreferrer" class="break-all font-semibold text-pitch-700 underline transition hover:text-gold-500">{{ $shop->website_url }}</a>
                </dd>
            @endif
        </dl>
    </section>

    {{-- 地図 --}}
    <section class="overflow-hidden rounded-xl border border-pitch-100 bg-white shadow-sm">
        @if ($mapsEmbedKey)
            <iframe
                src="{{ $shop->googleMapsEmbedUrl($mapsEmbedKey) }}"
                title="{{ $shop->name }}の地図"
                class="h-72 w-full border-0"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
            ></iframe>
        @else
            <div class="p-6 text-sm">
                <a href="{{ $shop->googleMapsSearchUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-pitch-700 underline transition hover:text-gold-500">Google マップで開く</a>
            </div>
        @endif
    </section>

    {{-- 座席情報（details / summary で開閉する） --}}
    <details class="group rounded-xl border border-pitch-100 bg-white shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 [&::-webkit-details-marker]:hidden">
            <h2 class="text-lg font-bold text-pitch-950">座席情報<span class="ml-2 text-sm font-semibold text-pitch-500">（{{ $seatTypes->count() }}件）</span></h2>
            <span class="text-pitch-500 transition group-open:rotate-180" aria-hidden="true">▼</span>
        </summary>
        <div class="px-6 pb-6">
            @if ($seatTypes->isEmpty())
                <p class="text-sm text-pitch-700">座席情報は準備中です。</p>
            @else
                <ul class="divide-y divide-pitch-100">
                    @foreach ($seatTypes as $seatType)
                        <li class="flex items-start justify-between gap-4 py-3">
                            <span class="flex flex-col gap-1">
                                <span class="font-semibold text-pitch-950">{{ $seatType->name }}</span>
                                @if ($seatType->description)
                                    <span class="text-sm text-pitch-700">{{ $seatType->description }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 font-bold text-pitch-950">{{ number_format($seatType->default_price) }}円</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-pitch-500">※ 基本料金です。試合ごとの料金は試合詳細でご確認ください。</p>
            @endif
        </div>
    </details>

    {{-- 放映予定試合（details / summary で開閉する） --}}
    <details class="group rounded-xl border border-pitch-100 bg-white shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 [&::-webkit-details-marker]:hidden">
            <h2 class="text-lg font-bold text-pitch-950">放映予定試合<span class="ml-2 text-sm font-semibold text-pitch-500">（{{ $upcomingMatches->count() }}件）</span></h2>
            <span class="text-pitch-500 transition group-open:rotate-180" aria-hidden="true">▼</span>
        </summary>
        <div class="px-6 pb-6">
            @if ($upcomingMatches->isEmpty())
                <p class="text-sm text-pitch-700">放映予定の試合はありません。</p>
            @else
                <ul class="divide-y divide-pitch-100">
                    @foreach ($upcomingMatches as $footballMatch)
                        <li>
                            <a href="{{ route('matches.show', $footballMatch->id) }}" class="flex flex-col gap-1 py-3 transition hover:text-gold-500 sm:flex-row sm:items-center sm:gap-4">
                                <span class="shrink-0 text-sm text-pitch-500">{{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</span>
                                <span class="shrink-0 text-xs font-bold text-pitch-700">{{ $footballMatch->competition->displayName() }}</span>
                                <span class="font-semibold">{{ $footballMatch->homeTeam->displayName() }} vs {{ $footballMatch->awayTeam->displayName() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </details>
</div>
@endsection
