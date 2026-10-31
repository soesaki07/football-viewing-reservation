{{-- マイページの予約一覧（今後・過去で共通） --}}
@if ($reservations->isEmpty())
    <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">{{ $emptyMessage }}</p>
@else
    <ul class="space-y-4">
        @foreach ($reservations as $reservation)
            @php
                $match = $reservation->broadcastSeatType->broadcast->footballMatch;
                $shop = $reservation->broadcastSeatType->broadcast->shop;
            @endphp
            <li class="rounded-xl border border-pitch-100 bg-white p-5 shadow-sm">
                <div class="mb-2 flex flex-wrap items-center gap-3">
                    <span class="font-bold text-pitch-950">{{ $match->homeTeam->name }} vs {{ $match->awayTeam->name }}</span>
                    <span class="rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold text-pitch-950">{{ $reservation->statusLabel() }}</span>
                </div>
                <dl class="grid gap-1 text-sm text-pitch-700 sm:grid-cols-[10rem_1fr]">
                    <dt class="font-semibold text-pitch-500">キックオフ（日本時間）</dt>
                    <dd>{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
                    <dt class="font-semibold text-pitch-500">店舗</dt>
                    <dd>{{ $shop->name }}</dd>
                    <dt class="font-semibold text-pitch-500">座席</dt>
                    <dd>{{ $reservation->broadcastSeatType->seatType->name }}</dd>
                    <dt class="font-semibold text-pitch-500">人数</dt>
                    <dd>{{ $reservation->number_of_people }}人</dd>
                    <dt class="font-semibold text-pitch-500">合計金額</dt>
                    <dd>{{ number_format($reservation->total_price) }}円</dd>
                    <dt class="font-semibold text-pitch-500">予約コード</dt>
                    <dd class="font-bold tracking-widest text-pitch-950">{{ $reservation->reservation_code }}</dd>
                </dl>
                <a href="{{ route('reservations.show', $reservation) }}" class="mt-3 inline-block text-sm font-bold text-pitch-700 underline transition hover:text-gold-500">予約詳細を見る</a>
            </li>
        @endforeach
    </ul>
@endif
