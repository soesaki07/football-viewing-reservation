@extends('layouts.owner')
@section('title', '予約管理')
@if (! $shops->isEmpty())
@section('shop-switcher')
<x-owner.shop-switcher :shops="$shops" :current="$shop" />
@endsection
@endif
@section('content')
<div class="space-y-6">
    <x-page-header title="予約管理" eyebrow="Partner Console" />

    @if ($shops->isEmpty())
        <x-card class="text-sm text-on-surface-variant">店舗が登録されていません。</x-card>
    @else
        @if (session('success'))
            <x-alert variant="success">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="error">{{ session('error') }}</x-alert>
        @endif
        @error('status')
            <x-alert variant="error">{{ $message }}</x-alert>
        @enderror

        <p class="text-sm font-semibold text-on-surface-variant">店舗：<span class="text-on-surface">{{ $shop->name }}</span></p>

        {{-- 今後・過去のタブ --}}
        <nav class="flex gap-2" aria-label="表示期間">
            @foreach (['upcoming' => '今後', 'past' => '過去'] as $periodKey => $periodLabel)
                <a href="{{ route('owner.reservations.index', ['shop' => $shop->id, 'period' => $periodKey]) }}"
                    @class([
                        'rounded-lg px-4 py-2 text-sm font-bold transition',
                        'bg-primary-container text-on-primary' => $period === $periodKey,
                        'bg-surface-container text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' => $period !== $periodKey,
                    ])
                    @if ($period === $periodKey) aria-current="page" @endif>{{ $periodLabel }}</a>
            @endforeach
        </nav>

        {{-- 試合での絞り込み --}}
        <form method="GET" action="{{ route('owner.reservations.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <input type="hidden" name="shop" value="{{ $shop->id }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <div class="min-w-0 sm:max-w-xl sm:flex-1">
                <x-form.field name="match" type="select" label="試合">
                    <option value="">すべての試合</option>
                    @foreach ($matchOptions as $matchOption)
                        <option value="{{ $matchOption->id }}" @selected($selectedMatchId === $matchOption->id)>
                            {{ $matchOption->homeTeam->displayName() }} vs {{ $matchOption->awayTeam->displayName() }}（{{ $matchOption->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）
                        </option>
                    @endforeach
                </x-form.field>
            </div>
            <x-button>絞り込む</x-button>
        </form>

        @if ($reservations->isEmpty())
            <x-card class="text-sm text-on-surface-variant">条件に合う予約はありません。</x-card>
        @else
            <x-owner.table>
                <thead class="bg-surface-container-low text-xs font-semibold text-on-surface-variant">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">予約コード</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ユーザー</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">試合</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">座席</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">人数</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">予約状態</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @foreach ($reservations as $reservation)
                        @php
                            $match = $reservation->broadcastSeatType->broadcast->footballMatch;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-bold tracking-widest text-on-surface">
                                <a href="{{ route('owner.reservations.show', ['reservation' => $reservation, 'period' => $period, 'match' => $selectedMatchId, 'page' => $reservations->currentPage()]) }}" class="underline transition hover:text-secondary">{{ $reservation->reservation_code }}</a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $reservation->user->name }}</td>
                            <td class="min-w-48 px-4 py-3">
                                <span class="font-bold text-on-surface">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}</span><br>
                                <span class="whitespace-nowrap text-xs">{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $reservation->broadcastSeatType->seatType->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $reservation->number_of_people }}人</td>
                            <td class="whitespace-nowrap px-4 py-3"><x-owner.reservation-status-badge :reservation="$reservation" />
                                @foreach ($reservation->availableVisitStatusChanges() as $nextStatus)
                                    <form method="POST" action="{{ route('owner.reservations.visit-status', $reservation) }}" data-confirm="予約 {{ $reservation->reservation_code }} を{{ \App\Models\Reservation::visitStatusLabel($nextStatus) }}にしますか？" class="ml-2 inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                                        <input type="hidden" name="period" value="{{ $period }}">
                                        @if ($selectedMatchId !== null)
                                            <input type="hidden" name="match" value="{{ $selectedMatchId }}">
                                        @endif
                                        <input type="hidden" name="page" value="{{ $reservations->currentPage() }}">
                                        <x-button variant="secondary" size="sm">{{ $reservation->visitStatusButtonLabel($nextStatus) }}</x-button>
                                    </form>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-owner.table>

            {{ $reservations->links() }}
        @endif
    @endif
</div>
@endsection
