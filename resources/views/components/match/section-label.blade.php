{{-- 小さな見出し（金色・大文字・字間広め）。中身はそのまま出す --}}
<h4 {{ $attributes->merge(['class' => 'text-[11px] font-bold uppercase tracking-widest text-secondary']) }}>{{ $slot }}</h4>
