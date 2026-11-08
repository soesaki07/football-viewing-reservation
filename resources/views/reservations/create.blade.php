@extends('layouts.app')
@section('title', '予約入力')
@section('content')
@php
    $selectedSeatType = (int) old('broadcast_seat_type_id', $input['broadcast_seat_type_id'] ?? 0);
    $numberOfPeople = $input['number_of_people'] ?? 1;
    $customerNote = $input['customer_note'] ?? '';
@endphp
<div class="mx-auto max-w-3xl">
    <a href="{{ route('matches.show', $broadcast->football_match_id) }}" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-on-surface-variant transition hover:text-primary">← 試合詳細に戻る</a>

    <x-reservation.steps :current="1" class="mb-6" />

    <x-page-header title="予約入力" />

    @if (session('error'))
        <x-alert variant="error" class="mb-4">{{ session('error') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('reservations.confirm', $broadcast) }}" class="space-y-6 rounded-xl bg-surface-container-lowest p-4 shadow-sm ring-1 ring-outline-variant/40 sm:p-6">
        @csrf
        <x-reservation.match-summary :football-match="$broadcast->footballMatch" :shop="$broadcast->shop" />

        <fieldset>
            <legend class="mb-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant">座席種別</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($seatTypes as $seatType)
                    @php $remaining = $seatType->remainingSeats(); @endphp
                    <label class="relative block {{ $remaining > 0 ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                        <input type="radio" name="broadcast_seat_type_id" value="{{ $seatType->id }}"
                               data-price="{{ $seatType->price }}" class="peer sr-only"
                               @checked($selectedSeatType === $seatType->id) @disabled($remaining < 1)>
                        <span class="flex h-full flex-col justify-between gap-4 rounded-xl p-4 ring-1 ring-outline-variant transition peer-checked:bg-primary-fixed/40 peer-checked:ring-2 peer-checked:ring-primary-container peer-focus-visible:ring-2 peer-focus-visible:ring-secondary {{ $remaining > 0 ? 'bg-surface-container-lowest hover:bg-surface-container-low' : 'bg-surface-container-low text-on-surface-variant' }}">
                            <span class="flex items-start justify-between gap-2">
                                <span class="font-headline text-base font-semibold text-primary">{{ $seatType->seatType->name }}</span>
                                @if ($remaining > 0)
                                    <span class="shrink-0 text-xs font-bold text-secondary">残り{{ $remaining }}席</span>
                                @else
                                    <x-badge variant="danger" class="shrink-0">満席</x-badge>
                                @endif
                            </span>
                            <span class="flex flex-col gap-1">
                                <span class="text-sm font-bold text-on-surface">1人 {{ number_format($seatType->price) }}円</span>
                                @if ($remaining > 0)
                                    <span class="text-xs text-on-surface-variant">1回{{ $seatType->max_people_per_reservation }}人まで</span>
                                @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('broadcast_seat_type_id')
                <p class="mt-2 text-xs font-semibold text-error">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-form.field name="number_of_people" label="人数" type="number" min="1" :value="$numberOfPeople" class="sm:max-w-40" />

        <x-form.field name="customer_note" label="備考（任意・200文字以内）" type="textarea" rows="3" maxlength="200" :value="$customerNote" />

        <div class="flex flex-col gap-4 border-t border-outline-variant/50 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-headline text-lg font-semibold text-primary">合計金額 <span id="total-price">-</span></p>
            <x-button type="submit">確認画面へ</x-button>
        </div>
    </form>
</div>

<script>
    // 合計金額の表示用（確定時の金額はサーバー側で計算する）
    (function () {
        const radios = document.querySelectorAll('input[name="broadcast_seat_type_id"]');
        const people = document.getElementById('number_of_people');
        const total = document.getElementById('total-price');
        function update() {
            const checked = document.querySelector('input[name="broadcast_seat_type_id"]:checked');
            const count = parseInt(people.value, 10);
            if (!checked || !(count >= 1)) {
                total.textContent = '-';
                return;
            }
            total.textContent = (parseInt(checked.dataset.price, 10) * count).toLocaleString('ja-JP') + '円';
        }
        radios.forEach(function (radio) { radio.addEventListener('change', update); });
        people.addEventListener('input', update);
        update();
    })();
</script>
@endsection
