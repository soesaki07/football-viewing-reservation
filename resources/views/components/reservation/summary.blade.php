{{-- 予約内容のまとめ（座席・人数・単価・合計・備考・予約日時など）。渡さなかった項目は出さない。予約コードは含めない（確認画面にはまだない） --}}
@props(['seatName', 'numberOfPeople', 'totalPrice', 'unitPrice' => null, 'note' => null, 'reservedAt' => null, 'cancelledAt' => null])
<dl {{ $attributes->merge(['class' => 'grid gap-x-4 gap-y-3 text-sm sm:grid-cols-[8rem_1fr]']) }}>
    <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">座席</dt>
    <dd class="text-on-surface">{{ $seatName }}</dd>
    <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">人数</dt>
    <dd class="text-on-surface">{{ $numberOfPeople }}人</dd>
    @if ($unitPrice !== null)
        <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">単価</dt>
        <dd class="text-on-surface">{{ number_format($unitPrice) }}円</dd>
    @endif
    <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">合計金額</dt>
    <dd class="font-headline text-lg font-semibold text-primary">{{ number_format($totalPrice) }}円</dd>
    @if ($note !== null && $note !== '')
        <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">備考</dt>
        <dd class="whitespace-pre-line break-words text-on-surface">{{ $note }}</dd>
    @endif
    @if ($reservedAt)
        <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">予約日時</dt>
        <dd class="text-on-surface">{{ $reservedAt->copy()->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
    @endif
    @if ($cancelledAt)
        <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">キャンセル日時</dt>
        <dd class="text-on-surface">{{ $cancelledAt->copy()->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
    @endif
</dl>
