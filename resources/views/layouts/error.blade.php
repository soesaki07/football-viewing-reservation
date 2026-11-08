<!DOCTYPE html>
<html lang="ja">
    <head>
        @include('layouts._head')
    </head>
    {{-- エラー画面用：例外の処理の中で表示されるため、ログイン状態・セッションを参照しない（ナビなし） --}}
    <body class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <header class="bg-surface/90 shadow-sm">
            <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-center px-4 py-3 sm:px-6">
                @include('layouts._logo')
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-2xl flex-1 items-center justify-center px-4 py-12 sm:px-6">
            <div class="w-full rounded-xl bg-surface-container-lowest p-8 text-center shadow-lg sm:p-12">
                <p class="font-headline text-6xl font-bold tracking-tighter text-primary-container sm:text-7xl">@yield('code')</p>
                <h1 class="mt-4 font-headline text-2xl font-semibold tracking-tight text-primary">@yield('heading')</h1>
                <p class="mt-4 text-sm leading-relaxed text-on-surface-variant">@yield('message')</p>
                <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row">
                    <x-button :href="url('/')">試合一覧へ戻る</x-button>
                    @hasSection('back')
                        {{-- JS が動かないときは、リンクとして試合一覧へ移る --}}
                        <x-button variant="secondary" :href="url('/')" data-history-back>前のページへ戻る</x-button>
                    @endif
                </div>
            </div>
        </main>

        @include('layouts._footer')
    </body>
</html>
