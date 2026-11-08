{{-- 一覧の表。スマホ幅では、ページ全体ではなく表の部分だけを横にスクロールさせる。中身（thead・tbody）は呼び出す側が書く --}}
<div data-testid="owner-table-scroll" {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40']) }}>
    <table class="min-w-full text-left text-sm text-on-surface-variant">{{ $slot }}</table>
</div>
