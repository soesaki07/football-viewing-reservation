<!DOCTYPE html>
<html lang="ja">
    <head>
        @include('layouts._head')
    </head>
    <body class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <header class="sticky top-0 z-40 bg-surface/90 shadow-sm backdrop-blur-md">
            <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                @include('layouts._logo')

                @auth
                    <nav data-testid="desktop-nav" class="hidden flex-wrap items-center justify-end gap-1 sm:flex">
                        @include('layouts._nav_links', [
                            'baseClass' => 'rounded-lg px-3 py-2 text-sm font-semibold transition',
                            'idleClass' => 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface',
                            'activeClass' => 'bg-primary-container text-on-primary',
                        ])
                    </nav>

                    {{-- スマホ幅（sm 未満）のメニュー。JavaScript を使わず details/summary で開閉する --}}
                    <details data-testid="mobile-menu" class="relative sm:hidden">
                        <summary aria-label="メニュー" class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg bg-primary-container text-2xl leading-none text-on-primary [&::-webkit-details-marker]:hidden">
                            <span aria-hidden="true">☰</span>
                        </summary>
                        <nav class="absolute right-0 z-10 mt-2 flex w-60 flex-col gap-1 rounded-xl bg-surface-container-lowest p-3 text-sm shadow-lg ring-1 ring-outline-variant">
                            @include('layouts._nav_links', [
                                'baseClass' => 'block w-full rounded-lg px-3 py-2 text-left text-sm font-semibold transition',
                                'idleClass' => 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface',
                                'activeClass' => 'bg-primary-container text-on-primary',
                            ])
                        </nav>
                    </details>
                @endauth

                @guest
                    {{-- 未ログイン：項目が2つだけなので、スマホ幅でもそのまま並べる --}}
                    <nav class="flex items-center gap-1">
                        <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('login') ? 'bg-primary-container text-on-primary' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">ログイン</a>
                        <a href="{{ route('register') }}" @if (request()->routeIs('register')) aria-current="page" @endif class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('register') ? 'bg-primary-container text-on-primary' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' }}">新規登録</a>
                    </nav>
                @endguest
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
            @yield('content')
        </main>

        @include('layouts._footer')
    </body>
</html>
