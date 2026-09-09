@extends('layouts.app')
@section('title', 'ログイン')
@section('content')
<div class="mx-auto max-w-md">
    <h1 class="mb-6 flex items-center justify-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">⚽</span>
        ログイン
    </h1>

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5 rounded-xl border border-pitch-100 bg-white p-8 shadow-sm">
        @csrf
        <div class="flex flex-col gap-1">
            <label class="text-sm font-semibold text-pitch-700">メールアドレス</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="rounded-lg border border-pitch-100 px-3 py-2 text-sm text-pitch-950 shadow-inner focus:border-transparent focus:outline-none focus:ring-2 focus:ring-gold-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-sm font-semibold text-pitch-700">パスワード</label>
            <input type="password" name="password" required
                   class="rounded-lg border border-pitch-100 px-3 py-2 text-sm text-pitch-950 shadow-inner focus:border-transparent focus:outline-none focus:ring-2 focus:ring-gold-500">
        </div>
        <button type="submit"
                class="mt-2 rounded-full bg-pitch-950 px-6 py-2.5 text-sm font-bold text-gold-400 transition hover:bg-pitch-800">
            ログイン
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-pitch-700">
        <a href="{{ route('register') }}" class="font-semibold transition hover:text-gold-500">アカウントがない方はこちら</a>
    </p>
</div>
@endsection
