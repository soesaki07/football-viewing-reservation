{{-- ページの見出し。eyebrow（小さな英字の見出し・任意）＋ title（h1）＋ description（説明文・任意）＋ 中身（右側の操作ボタン・任意） --}}
@props(['title', 'eyebrow' => null, 'description' => null])
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="flex flex-col gap-1">
        @if ($eyebrow)
            <p class="text-[11px] font-bold uppercase tracking-widest text-secondary">{{ $eyebrow }}</p>
        @endif
        <h1 class="font-headline text-2xl font-semibold tracking-tight text-primary sm:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="max-w-2xl text-sm text-on-surface-variant">{{ $description }}</p>
        @endif
    </div>
    @if ($slot->hasActualContent())
        <div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>
    @endif
</div>
