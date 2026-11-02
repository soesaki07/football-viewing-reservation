@extends('layouts.app')
@section('title', '放映管理')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">📺</span>
        放映管理
    </h1>

    @if ($shops->isEmpty())
        <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">店舗が登録されていません。</p>
    @else
        @if (session('success'))
            <p class="rounded-xl border border-pitch-100 bg-pitch-50 p-4 text-sm font-bold text-pitch-950" role="status">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ session('error') }}</p>
        @endif

        {{-- 店舗の切り替え（2店舗以上のときだけ） --}}
        @if ($shops->count() >= 2)
            <nav class="flex flex-wrap gap-2" aria-label="店舗の切り替え">
                @foreach ($shops as $ownedShop)
                    <a href="{{ route('owner.broadcasts.index', ['shop' => $ownedShop->id, 'period' => $period]) }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm font-bold transition',
                            'bg-pitch-800 text-white' => $ownedShop->id === $shop->id,
                            'border border-pitch-100 bg-white text-pitch-700 hover:text-gold-500' => $ownedShop->id !== $shop->id,
                        ])
                        @if ($ownedShop->id === $shop->id) aria-current="page" @endif>{{ $ownedShop->name }}</a>
                @endforeach
            </nav>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-pitch-500">店舗：<span class="text-pitch-950">{{ $shop->name }}</span></p>
            <a href="{{ route('owner.broadcasts.create', ['shop' => $shop->id]) }}" class="rounded-md bg-pitch-800 px-4 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">放映を登録する</a>
        </div>

        {{-- 今後・過去のタブ --}}
        <nav class="flex gap-2" aria-label="表示期間">
            @foreach (['upcoming' => '今後', 'past' => '過去'] as $periodKey => $periodLabel)
                <a href="{{ route('owner.broadcasts.index', ['shop' => $shop->id, 'period' => $periodKey]) }}"
                    @class([
                        'rounded-md px-4 py-2 text-sm font-bold transition',
                        'bg-pitch-800 text-white' => $period === $periodKey,
                        'border border-pitch-100 bg-white text-pitch-700 hover:text-gold-500' => $period !== $periodKey,
                    ])
                    @if ($period === $periodKey) aria-current="page" @endif>{{ $periodLabel }}</a>
            @endforeach
        </nav>

        @if ($broadcasts->isEmpty())
            <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">条件に合う放映はありません。</p>
        @else
            {{-- スマホ幅ではテーブルの中だけを横スクロールさせる --}}
            <div class="overflow-x-auto rounded-xl border border-pitch-100 bg-white shadow-sm">
                <table class="min-w-full text-left text-sm text-pitch-700">
                    <thead class="bg-pitch-50 text-xs font-semibold text-pitch-500">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">試合</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">受付期間</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">ステータス</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">座席</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">予約数</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3"><span class="sr-only">操作</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pitch-100">
                        @foreach ($broadcasts as $broadcast)
                            @php
                                $match = $broadcast->footballMatch;
                                $totalCapacity = $broadcast->broadcastSeatTypes->sum('capacity');
                                $totalReserved = $broadcast->broadcastSeatTypes->sum(fn ($seat) => (int) $seat->occupied_people);
                            @endphp
                            <tr>
                                <td class="min-w-48 px-4 py-3">
                                    <span class="font-bold text-pitch-950">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}</span><br>
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
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold text-pitch-950">{{ $broadcast->status === \App\Models\Broadcast::STATUS_PUBLISHED ? '公開' : '非公開' }}</span></td>
                                <td class="px-4 py-3 text-xs">
                                    @foreach ($broadcast->broadcastSeatTypes as $seat)
                                        <p class="whitespace-nowrap">{{ $seat->seatType->name }}：{{ $seat->capacity }}席 / {{ number_format($seat->price) }}円</p>
                                    @endforeach
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $totalReserved }} / {{ $totalCapacity }}人</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    {{-- 終了した試合の放映は編集できないので、リンクを出さない --}}
                                    @unless ($match->isFinished())
                                        <a href="{{ route('owner.broadcasts.edit', $broadcast) }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">編集</a>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $broadcasts->links() }}
        @endif
    @endif
</div>
@endsection
