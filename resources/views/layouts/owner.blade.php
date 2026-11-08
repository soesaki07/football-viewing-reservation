<!DOCTYPE html>
<html lang="ja">
    <head>
        @include('layouts._head')
    </head>
    <body class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        {{-- 店舗オーナー用のレイアウト（Partner Portal）。店舗の切り替えは、各画面が @section('shop-switcher') に <x-owner.shop-switcher> を書いたときだけ出る（変数の有無では決めない） --}}
        <header class="sticky top-0 z-40 bg-surface/90 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex min-h-16 max-w-6xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-6">
                <a href="{{ url('/') }}" class="order-1 flex flex-col leading-none">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-secondary">Partner Portal</span>
                    <span class="mt-1 font-headline text-lg font-bold tracking-tight text-primary sm:text-xl">PITCH &amp; PARLOUR</span>
                </a>

                @hasSection('shop-switcher')
                    <div class="order-3 basis-full lg:order-2 lg:basis-auto">
                        @yield('shop-switcher')
                    </div>
                @endif

                <nav data-testid="desktop-nav" class="order-2 ml-auto hidden flex-wrap items-center justify-end gap-1 lg:order-3 lg:flex">
                    <x-owner.nav-links
                        base-class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                        idle-class="text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
                        active-class="bg-primary-container text-on-primary" />
                </nav>

                {{-- スマホ幅（lg 未満）のメニュー。JavaScript を使わず details/summary で開閉する --}}
                <details data-testid="mobile-menu" class="relative order-2 ml-auto lg:hidden">
                    <summary aria-label="メニュー" class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg bg-primary-container text-2xl leading-none text-on-primary [&::-webkit-details-marker]:hidden">
                        <span aria-hidden="true">☰</span>
                    </summary>
                    <nav class="absolute right-0 z-30 mt-2 flex w-60 flex-col gap-1 rounded-xl bg-surface-container-lowest p-3 text-sm shadow-lg ring-1 ring-outline-variant">
                        <x-owner.nav-links
                            base-class="block w-full rounded-lg px-3 py-2 text-left text-sm font-semibold transition"
                            idle-class="text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
                            active-class="bg-primary-container text-on-primary" />
                    </nav>
                </details>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
            @yield('content')
        </main>

        @include('layouts._footer')
    </body>
</html>
