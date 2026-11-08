@extends('layouts.app')
@section('title', $shop->name)
@section('content')
<div class="mx-auto flex max-w-3xl flex-col gap-6">
    <x-page-header eyebrow="Shop" :title="$shop->name" />

    {{-- 店舗情報 --}}
    <x-card>
        @if ($shop->description)
            <p class="mb-5 whitespace-pre-line text-sm text-on-surface-variant">{{ $shop->description }}</p>
        @endif
        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-4 text-sm">
            <dt class="text-[11px] font-bold uppercase tracking-widest text-secondary">住所</dt>
            <dd class="break-words text-on-surface">
                @if ($shop->postal_code)
                    〒{{ $shop->postal_code }}<br>
                @endif
                {{ $shop->fullAddress() }}
            </dd>
            @if ($shop->phone_number)
                <dt class="text-[11px] font-bold uppercase tracking-widest text-secondary">電話番号</dt>
                <dd class="text-on-surface">{{ $shop->phone_number }}</dd>
            @endif
            @if ($shop->businessHoursLabel())
                <dt class="text-[11px] font-bold uppercase tracking-widest text-secondary">営業時間</dt>
                <dd class="text-on-surface">{{ $shop->businessHoursLabel() }}</dd>
            @endif
            @if ($shop->hasSafeWebsiteUrl())
                <dt class="text-[11px] font-bold uppercase tracking-widest text-secondary">Webサイト</dt>
                <dd class="min-w-0">
                    <a href="{{ $shop->website_url }}" target="_blank" rel="noopener noreferrer" class="break-all font-semibold text-primary underline transition hover:text-secondary">{{ $shop->website_url }}</a>
                </dd>
            @endif
        </dl>
    </x-card>

    {{-- 地図 --}}
    <section class="overflow-hidden rounded-xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
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
                <a href="{{ $shop->googleMapsSearchUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary underline transition hover:text-secondary">Google マップで開く</a>
            </div>
        @endif
    </section>

    {{-- 座席情報（details / summary で開閉する） --}}
    <details class="group rounded-xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl p-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary [&::-webkit-details-marker]:hidden">
            <h2 class="font-headline text-lg font-semibold text-primary">座席情報<span class="ml-2 text-sm font-semibold text-on-surface-variant">（{{ $seatTypes->count() }}件）</span></h2>
            <span class="text-outline transition group-open:rotate-180" aria-hidden="true">▼</span>
        </summary>
        <div class="px-6 pb-6">
            @if ($seatTypes->isEmpty())
                <p class="text-sm text-on-surface-variant">座席情報は準備中です。</p>
            @else
                <ul class="divide-y divide-outline-variant/50">
                    @foreach ($seatTypes as $seatType)
                        <li class="flex items-start justify-between gap-4 py-3">
                            <span class="flex min-w-0 flex-col gap-1">
                                <span class="font-semibold text-on-surface">{{ $seatType->name }}</span>
                                @if ($seatType->description)
                                    <span class="text-sm text-on-surface-variant">{{ $seatType->description }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 font-bold text-primary">{{ number_format($seatType->default_price) }}円</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-on-surface-variant">※ 基本料金です。試合ごとの料金は試合詳細でご確認ください。</p>
            @endif
        </div>
    </details>

    {{-- 放映予定試合（details / summary で開閉する） --}}
    <details class="group rounded-xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl p-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary [&::-webkit-details-marker]:hidden">
            <h2 class="font-headline text-lg font-semibold text-primary">放映予定試合<span class="ml-2 text-sm font-semibold text-on-surface-variant">（{{ $upcomingMatches->count() }}件）</span></h2>
            <span class="text-outline transition group-open:rotate-180" aria-hidden="true">▼</span>
        </summary>
        <div class="px-6 pb-6">
            @if ($upcomingMatches->isEmpty())
                <p class="text-sm text-on-surface-variant">放映予定の試合はありません。</p>
            @else
                <ul class="divide-y divide-outline-variant/50">
                    @foreach ($upcomingMatches as $footballMatch)
                        <li>
                            <a href="{{ route('matches.show', $footballMatch->id) }}" class="flex flex-col gap-1 py-3 text-on-surface transition hover:text-secondary sm:flex-row sm:items-center sm:gap-4">
                                <span class="shrink-0 text-sm text-on-surface-variant">{{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</span>
                                <span class="shrink-0 text-xs font-bold text-secondary">{{ $footballMatch->competition->displayName() }}</span>
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
