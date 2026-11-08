{{-- 店舗の切り替え（ヘッダーのプルダウン）。JavaScript を使わず details/summary で開閉する。
    各項目は、今の画面の URL に ?shop= を付けた GET のリンク。表示期間（period）だけは引き継ぎ、試合の絞り込み・ページ番号・大会の選択は店舗ごとに意味が変わるので引き継がない。
    店舗が1つのとき、URL に ID（編集・詳細）を持つ画面のときは何も出さない。$shops：自分の店舗（id 順）、$current：選択中の店舗 --}}
@props(['shops', 'current'])
@php
    $route = request()->route();
    $period = request()->query('period');
    $canSwitch = $shops->count() >= 2 && $route !== null && $route->getName() !== null && $route->parameterNames() === [];
@endphp
@if ($canSwitch)
    <nav aria-label="店舗の切り替え" data-testid="shop-switcher" {{ $attributes }}>
        <details class="relative">
            <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg bg-surface-container-low px-3 py-1.5 text-left hover:bg-surface-container [&::-webkit-details-marker]:hidden">
                <span class="flex min-w-0 flex-col leading-tight">
                    <span class="text-[11px] text-on-surface-variant">店舗</span>
                    <span class="max-w-[14rem] truncate text-sm font-semibold text-on-surface">{{ $current->name }}</span>
                </span>
                <span aria-hidden="true" class="text-on-surface-variant">▾</span>
            </summary>
            <ul class="absolute left-0 z-20 mt-2 max-h-72 w-64 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-xl bg-surface-container-lowest p-2 shadow-lg ring-1 ring-outline-variant">
                @foreach ($shops as $ownedShop)
                    @php
                        $isCurrent = $ownedShop->id === $current->id;
                        $query = ['shop' => $ownedShop->id];
                        if (in_array($period, ['upcoming', 'past'], true)) {
                            $query['period'] = $period;
                        }
                    @endphp
                    <li>
                        <a href="{{ route($route->getName(), $query) }}" @if ($isCurrent) aria-current="page" @endif
                            class="block truncate rounded-lg px-3 py-2 text-sm font-semibold transition {{ $isCurrent ? 'bg-primary-container text-on-primary' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">{{ $ownedShop->name }}</a>
                    </li>
                @endforeach
            </ul>
        </details>
    </nav>
@endif
