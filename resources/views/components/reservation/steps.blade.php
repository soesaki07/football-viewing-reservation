{{-- 予約の3ステップの表示。current：今のステップ（1＝予約情報入力、2＝内容確認、3＝予約完了）。今のステップの項目に aria-current="step" が付く --}}
@props(['current' => 1])
@php
    $steps = [1 => '予約情報入力', 2 => '内容確認', 3 => '予約完了'];
@endphp
<ol aria-label="予約の流れ" data-testid="reservation-steps" {{ $attributes->merge(['class' => 'flex items-start justify-between gap-2 rounded-xl bg-surface-container-lowest p-4 shadow-sm ring-1 ring-outline-variant/40 sm:items-center sm:p-5']) }}>
    @foreach ($steps as $number => $label)
        @php
            $isCurrent = $number === (int) $current;
            $isDone = $number < (int) $current;
        @endphp
        <li @if ($isCurrent) aria-current="step" @endif class="flex flex-1 flex-col items-center gap-2 text-center sm:flex-row sm:text-left {{ $loop->last ? 'sm:flex-none' : '' }}">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isCurrent || $isDone ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface-variant' }}">{{ $number }}</span>
            <span class="flex flex-col">
                <span class="text-[10px] font-bold uppercase tracking-widest {{ $isCurrent ? 'text-secondary' : 'text-outline' }}">STEP {{ $number }}</span>
                <span class="text-xs sm:text-sm {{ $isCurrent ? 'font-bold text-on-surface' : 'text-on-surface-variant' }}">{{ $label }}</span>
            </span>
            @if (! $loop->last)
                {{-- ステップの間の線（飾りなので読み上げない） --}}
                <span aria-hidden="true" class="mx-3 hidden h-0.5 flex-1 sm:block {{ $isDone ? 'bg-primary-container' : 'bg-surface-container-high' }}"></span>
            @endif
        </li>
    @endforeach
</ol>
