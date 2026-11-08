{{-- マイページの予約一覧（今後・過去で共通） --}}
@if ($reservations->isEmpty())
    <x-card class="text-sm text-on-surface-variant">{{ $emptyMessage }}</x-card>
@else
    <ul class="space-y-4">
        @foreach ($reservations as $reservation)
            @php
                $match = $reservation->broadcastSeatType->broadcast->footballMatch;
                $shop = $reservation->broadcastSeatType->broadcast->shop;
            @endphp
            <li>
                <x-card class="space-y-4 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-reservation.status-badge :reservation="$reservation" />
                        <span class="break-all font-headline text-sm font-bold tracking-widest text-primary">{{ $reservation->reservation_code }}</span>
                    </div>
                    <x-reservation.match-summary :football-match="$match" :shop="$shop" />
                    <x-reservation.summary
                        :seat-name="$reservation->broadcastSeatType->seatType->name"
                        :number-of-people="$reservation->number_of_people"
                        :total-price="$reservation->total_price" />
                    <x-button variant="secondary" size="sm" :href="route('reservations.show', $reservation)">予約詳細を見る</x-button>
                </x-card>
            </li>
        @endforeach
    </ul>
@endif
