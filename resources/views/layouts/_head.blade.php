{{-- 全レイアウト共通の <head>。アプリ名は固定の文字列（env の APP_NAME に依存しない） --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>@yield('title') - PITCH &amp; PARLOUR</title>

{{-- 英数字のフォント（日本語は app.css で指定した日本語フォントで表示する） --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Epilogue:wght@500;600;700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
