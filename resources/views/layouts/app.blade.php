<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-pitch-50 font-sans text-pitch-950 antialiased">
        <header class="bg-gradient-to-r from-pitch-950 via-pitch-800 to-pitch-700 text-white shadow-lg">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-bold tracking-tight">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gold-500 text-pitch-950">
                        ⚽
                    </span>
                    <span>欧州サッカー観戦予約</span>
                </a>

                @auth
                    <nav class="hidden gap-6 text-sm font-medium text-pitch-100 sm:flex">
                        @include('layouts._nav_links', ['linkClass' => 'text-sm font-medium text-pitch-100 transition hover:text-gold-400'])
                    </nav>

                    {{-- スマホ幅（sm 未満）のメニュー。JavaScript を使わず details/summary で開閉する --}}
                    <details class="relative sm:hidden">
                        <summary aria-label="メニュー" class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-md text-2xl leading-none hover:bg-white/10 [&::-webkit-details-marker]:hidden">
                            <span aria-hidden="true">☰</span>
                            <span class="sr-only">メニュー</span>
                        </summary>
                        <nav class="absolute right-0 z-10 mt-2 flex w-60 flex-col gap-1 rounded-lg bg-pitch-950 p-3 text-sm shadow-lg ring-1 ring-white/10">
                            @include('layouts._nav_links', ['linkClass' => 'block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-pitch-100 transition hover:bg-white/10 hover:text-gold-400'])
                        </nav>
                    </details>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            @yield('content')
        </main>

        <footer class="mt-16 border-t border-pitch-100 bg-white py-6 text-center text-sm text-pitch-700">
            &copy; {{ date('Y') }} 欧州サッカー観戦予約
        </footer>
    </body>
</html>
