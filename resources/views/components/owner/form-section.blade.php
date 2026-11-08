{{-- フォームの項目のまとまり。title：まとまりの見出し（h2）。中身は入力欄 --}}
@props(['title'])
<section {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <h2 class="border-b border-outline-variant/60 pb-2 font-headline text-lg font-semibold text-primary">{{ $title }}</h2>
    {{ $slot }}
</section>
