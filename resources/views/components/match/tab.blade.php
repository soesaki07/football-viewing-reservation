{{-- 切り替えのリンク（並び順・今後／過去の試合）。href：リンク先、active：今選んでいる項目か。
    押すと別の表示に移るリンクなので aria-current は部品では付けない（必要なら呼び出し側で attributes に渡す） --}}
@props(['href', 'active' => false])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-4 py-1.5 text-sm font-bold transition '.($active ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface')]) }}>{{ $slot }}</a>
