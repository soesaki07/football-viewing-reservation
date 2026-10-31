@extends('layouts.app')
@section('title', '予約入力')
@section('content')
<h1 class="mb-6 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
    <span class="text-gold-500">🍺</span>
    予約入力
</h1>
@php
    $selectedSeatType = (int) old('broadcast_seat_type_id', $input['broadcast_seat_type_id'] ?? 0);
    $numberOfPeople = old('number_of_people', $input['number_of_people'] ?? 1);
    $customerNote = old('customer_note', $input['customer_note'] ?? '');
@endphp
<div class="mx-auto max-w-3xl">
    <a href="{{ route('matches.show', $broadcast->football_match_id) }}" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-pitch-700 transition hover:text-gold-500">← 試合詳細に戻る</a>

    @if (session('error'))
        <p class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">{{ session('error') }}</p>
    @endif

    <form method="POST" action="{{ route('reservations.confirm', $broadcast) }}" class="space-y-6 rounded-xl border border-pitch-100 bg-white p-6 shadow-sm">
        @csrf
        @include('reservations._match_summary', ['footballMatch' => $broadcast->footballMatch, 'shop' => $broadcast->shop])

        <fieldset>
            <legend class="mb-2 text-sm font-bold text-pitch-950">座席種別</legend>
            <div class="space-y-2">
                @foreach ($seatTypes as $seatType)
                    @php $remaining = $seatType->remainingSeats(); @endphp
                    <label class="flex items-center justify-between gap-3 rounded-lg border border-pitch-100 p-3 {{ $remaining > 0 ? 'cursor-pointer' : 'bg-gray-50 text-gray-500' }}">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="broadcast_seat_type_id" value="{{ $seatType->id }}"
                                   data-price="{{ $seatType->price }}" data-max="{{ min($seatType->max_people_per_reservation, $remaining) }}"
                                   @checked($selectedSeatType === $seatType->id) @disabled($remaining < 1)>
                            <span class="font-bold">{{ $seatType->seatType->name }}</span>
                        </span>
                        <span class="text-sm">
                            1人 {{ number_format($seatType->price) }}円
                            ／ @if ($remaining > 0) 残り{{ $remaining }}席（1回{{ $seatType->max_people_per_reservation }}人まで） @else <span class="font-bold text-red-700">満席</span> @endif
                        </span>
                    </label>
                @endforeach
            </div>
            @error('broadcast_seat_type_id')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <div>
            <label for="number_of_people" class="mb-2 block text-sm font-bold text-pitch-950">人数</label>
            <input id="number_of_people" type="number" name="number_of_people" min="1" value="{{ $numberOfPeople }}" class="w-32 rounded-lg border border-pitch-100 px-3 py-2">
            @error('number_of_people')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="customer_note" class="mb-2 block text-sm font-bold text-pitch-950">備考（任意・200文字以内）</label>
            <textarea id="customer_note" name="customer_note" rows="3" maxlength="200" class="w-full rounded-lg border border-pitch-100 px-3 py-2">{{ $customerNote }}</textarea>
            @error('customer_note')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-lg font-extrabold text-pitch-950">合計金額 <span id="total-price">-</span></p>

        <button type="submit" class="rounded-full bg-pitch-950 px-6 py-2 text-sm font-bold text-gold-400 transition hover:text-gold-300">確認画面へ</button>
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
