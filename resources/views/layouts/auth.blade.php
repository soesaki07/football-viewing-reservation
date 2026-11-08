<!DOCTYPE html>
<html lang="ja">
    <head>
        @include('layouts._head')
    </head>
    {{-- ログイン・登録用：ナビなし、中央のカード --}}
    <body class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <header class="bg-surface/90 shadow-sm">
            <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-center px-4 py-3 sm:px-6">
                @include('layouts._logo')
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 items-start justify-center px-4 py-10 sm:px-6 sm:py-16">
            <div class="w-full max-w-md">
                @yield('content')
            </div>
        </main>

        @include('layouts._footer')
    </body>
</html>
