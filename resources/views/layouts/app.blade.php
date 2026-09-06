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

                <nav class="hidden gap-6 text-sm font-medium text-pitch-100 sm:flex">
                    <a href="{{ url('/') }}" class="transition hover:text-gold-400">試合一覧</a>
                </nav>
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
