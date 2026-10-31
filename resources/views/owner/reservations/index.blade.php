@extends('layouts.app')
@section('title', '予約管理')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">📋</span>
        予約管理
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
        @error('status')
            <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ $message }}</p>
        @enderror

        {{-- 店舗の切り替え（2店舗以上のときだけ） --}}
        @if ($shops->count() >= 2)
            <nav class="flex flex-wrap gap-2" aria-label="店舗の切り替え">
                @foreach ($shops as $ownedShop)
                    <a href="{{ route('owner.reservations.index', ['shop' => $ownedShop->id, 'period' => $period]) }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm font-bold transition',
                            'bg-pitch-800 text-white' => $ownedShop->id === $shop->id,
                            'border border-pitch-100 bg-white text-pitch-700 hover:text-gold-500' => $ownedShop->id !== $shop->id,
                        ])
                        @if ($ownedShop->id === $shop->id) aria-current="page" @endif>{{ $ownedShop->name }}</a>
                @endforeach
            </nav>
        @endif

        <p class="text-sm font-semibold text-pitch-500">店舗：<span class="text-pitch-950">{{ $shop->name }}</span></p>

        {{-- 今後・過去のタブ --}}
        <nav class="flex gap-2" aria-label="表示期間">
            @foreach (['upcoming' => '今後', 'past' => '過去'] as $periodKey => $periodLabel)
                <a href="{{ route('owner.reservations.index', ['shop' => $shop->id, 'period' => $periodKey]) }}"
                    @class([
                        'rounded-md px-4 py-2 text-sm font-bold transition',
                        'bg-pitch-800 text-white' => $period === $periodKey,
                        'border border-pitch-100 bg-white text-pitch-700 hover:text-gold-500' => $period !== $periodKey,
                    ])
                    @if ($period === $periodKey) aria-current="page" @endif>{{ $periodLabel }}</a>
            @endforeach
        </nav>

        {{-- 試合での絞り込み --}}
        <form method="GET" action="{{ route('owner.reservations.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="shop" value="{{ $shop->id }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <label for="match" class="text-sm font-semibold text-pitch-500">試合</label>
            <select id="match" name="match" class="max-w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                <option value="">すべての試合</option>
                @foreach ($matchOptions as $matchOption)
                    <option value="{{ $matchOption->id }}" @selected($selectedMatchId === $matchOption->id)>
                        {{ $matchOption->homeTeam->displayName() }} vs {{ $matchOption->awayTeam->displayName() }}（{{ $matchOption->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）
                    </option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-pitch-800 px-4 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">絞り込む</button>
        </form>

        @if ($reservations->isEmpty())
            <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">条件に合う予約はありません。</p>
        @else
            {{-- スマホ幅ではテーブルの中だけを横スクロールさせる --}}
            <div class="overflow-x-auto rounded-xl border border-pitch-100 bg-white shadow-sm">
                <table class="min-w-full text-left text-sm text-pitch-700">
                    <thead class="bg-pitch-50 text-xs font-semibold text-pitch-500">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">予約コード</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">ユーザー</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">試合</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">座席</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">人数</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">予約状態</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pitch-100">
                        @foreach ($reservations as $reservation)
                            @php
                                $match = $reservation->broadcastSeatType->broadcast->footballMatch;
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-bold tracking-widest text-pitch-950">{{ $reservation->reservation_code }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $reservation->user->name }}</td>
                                <td class="min-w-48 px-4 py-3">
                                    <span class="font-bold text-pitch-950">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}</span><br>
                                    <span class="whitespace-nowrap text-xs">{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $reservation->broadcastSeatType->seatType->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $reservation->number_of_people }}人</td>
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold text-pitch-950">{{ $reservation->statusLabel() }}</span>
                                    @foreach ($reservation->availableVisitStatusChanges() as $nextStatus)
                                        @php
                                            $buttonLabel = match (true) {
                                                $nextStatus === 'visited' && $reservation->status === 'no_show' => '来店済みに変更',
                                                $nextStatus === 'no_show' && $reservation->status === 'visited' => '来店なしに変更',
                                                $nextStatus === 'visited' => '来店済み',
                                                default => '来店なし',
                                            };
                                        @endphp
                                        <form method="POST" action="{{ route('owner.reservations.visit-status', $reservation) }}" data-confirm="予約 {{ $reservation->reservation_code }} を{{ $nextStatus === 'visited' ? '来店済み' : '来店なし' }}にしますか？" class="ml-2 inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $nextStatus }}">
                                            <input type="hidden" name="period" value="{{ $period }}">
                                            @if ($selectedMatchId !== null)
                                                <input type="hidden" name="match" value="{{ $selectedMatchId }}">
                                            @endif
                                            <input type="hidden" name="page" value="{{ $reservations->currentPage() }}">
                                            <button type="submit" class="rounded-md border border-pitch-100 bg-white px-3 py-1 text-xs font-bold text-pitch-700 transition hover:text-gold-500">{{ $buttonLabel }}</button>
                                        </form>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $reservations->links() }}
        @endif
    @endif
</div>
@endsection
