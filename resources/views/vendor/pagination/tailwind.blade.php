@if ($paginator->hasPages())
    @php
        $itemClass = 'min-w-10 items-center justify-center rounded-lg px-3 py-2 text-sm font-bold transition';
        $linkClass = $itemClass.' bg-surface-container-lowest text-primary ring-1 ring-outline-variant/60 hover:bg-surface-container focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary';
        $disabledClass = $itemClass.' cursor-not-allowed bg-surface-container text-outline';
    @endphp
    <nav role="navigation" aria-label="ページ送り" class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
        <p class="text-sm text-on-surface-variant [&>span]:font-medium [&>span]:text-on-surface">
            {{-- 日本語の語順（「35件中 1〜20件を表示」）にするため、標準の英語の文を置き換えている --}}
            <span class="font-medium">{{ $paginator->total() }}</span>件中
            @if ($paginator->firstItem())
                <span class="font-medium">{{ $paginator->firstItem() }}</span>〜<span class="font-medium">{{ $paginator->lastItem() }}</span>件を表示
            @else
                {{ $paginator->count() }}件を表示
            @endif
        </p>

        <div class="flex flex-wrap items-center justify-center gap-2">
            {{-- 前へ --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex {{ $disabledClass }}">前へ</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex {{ $linkClass }}">前へ</a>
            @endif

            {{-- ページ番号（スマホ幅では省略して、前へ・次へだけにする） --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true" class="{{ $itemClass }} hidden text-outline sm:inline-flex">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $itemClass }} hidden bg-primary-container text-on-primary sm:inline-flex">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $linkClass }} hidden sm:inline-flex" aria-label="{{ $page }}ページ目へ移動">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- 次へ --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex {{ $linkClass }}">次へ</a>
            @else
                <span aria-disabled="true" class="inline-flex {{ $disabledClass }}">次へ</span>
            @endif
        </div>
    </nav>
@endif
