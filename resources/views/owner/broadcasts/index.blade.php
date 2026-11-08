@extends('layouts.owner')
@section('title', '放映管理')
@if (! $shops->isEmpty())
@section('shop-switcher')
<x-owner.shop-switcher :shops="$shops" :current="$shop" />
@endsection
@endif
@section('content')
<div class="space-y-6">
    @if ($shops->isEmpty())
        <x-page-header title="放映管理" eyebrow="Partner Console" />
        <x-card class="text-sm text-on-surface-variant">店舗が登録されていません。</x-card>
    @else
        <x-page-header title="放映管理" eyebrow="Partner Console">
            <x-button :href="route('owner.broadcasts.create', ['shop' => $shop->id])">放映を登録する</x-button>
        </x-page-header>

        @if (session('success'))
            <x-alert variant="success">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="error">{{ session('error') }}</x-alert>
        @endif

        <p class="text-sm font-semibold text-on-surface-variant">店舗：<span class="text-on-surface">{{ $shop->name }}</span></p>

        {{-- 今後・過去のタブ --}}
        <nav class="flex gap-2" aria-label="表示期間">
            @foreach (['upcoming' => '今後', 'past' => '過去'] as $periodKey => $periodLabel)
                <a href="{{ route('owner.broadcasts.index', ['shop' => $shop->id, 'period' => $periodKey]) }}"
                    @class([
                        'rounded-lg px-4 py-2 text-sm font-bold transition',
                        'bg-primary-container text-on-primary' => $period === $periodKey,
                        'bg-surface-container text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' => $period !== $periodKey,
                    ])
                    @if ($period === $periodKey) aria-current="page" @endif>{{ $periodLabel }}</a>
            @endforeach
        </nav>

        @if ($broadcasts->isEmpty())
            <x-card class="text-sm text-on-surface-variant">条件に合う放映はありません。</x-card>
        @else
            <x-owner.table>
                <thead class="bg-surface-container-low text-xs font-semibold text-on-surface-variant">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">試合</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">受付期間</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ステータス</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">座席</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">予約数</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3"><span class="sr-only">操作</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @foreach ($broadcasts as $broadcast)
                        @php
                            $match = $broadcast->footballMatch;
                            $totalCapacity = $broadcast->broadcastSeatTypes->sum('capacity');
                            $totalReserved = $broadcast->broadcastSeatTypes->sum(fn ($seat) => (int) $seat->occupied_people);
                        @endphp
                        <tr>
                            <td class="min-w-48 px-4 py-3">
                                <span class="font-bold text-on-surface">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}</span><br>
                                <span class="whitespace-nowrap text-xs">{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs">
                                @if ($broadcast->reservation_start_at === null && $broadcast->reservation_end_at === null)
                                    制限なし
                                @else
                                    {{ $broadcast->reservation_start_at !== null ? \Illuminate\Support\Carbon::parse($broadcast->reservation_start_at)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '制限なし' }}<br>
                                    〜 {{ $broadcast->reservation_end_at !== null ? \Illuminate\Support\Carbon::parse($broadcast->reservation_end_at)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '制限なし' }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3"><x-badge :variant="$broadcast->status === \App\Models\Broadcast::STATUS_PUBLISHED ? 'success' : 'neutral'">{{ $broadcast->status === \App\Models\Broadcast::STATUS_PUBLISHED ? '公開' : '非公開' }}</x-badge></td>
                            <td class="px-4 py-3 text-xs">
                                @foreach ($broadcast->broadcastSeatTypes as $seat)
                                    <p class="whitespace-nowrap">{{ $seat->seatType->name }}：{{ $seat->capacity }}席 / {{ number_format($seat->price) }}円</p>
                                @endforeach
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $totalReserved }} / {{ $totalCapacity }}人</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{-- 終了した試合の放映は編集できないので、リンクを出さない --}}
                                @unless ($match->isFinished())
                                    <a href="{{ route('owner.broadcasts.edit', $broadcast) }}" class="text-sm font-bold text-primary hover:text-secondary">編集</a>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-owner.table>

            {{ $broadcasts->links() }}
        @endif
    @endif
</div>
@endsection
