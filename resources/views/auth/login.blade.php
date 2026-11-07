@extends('layouts.auth')
@section('title', 'ログイン')
@section('content')
<h1 class="mb-6 text-center font-headline text-2xl font-semibold tracking-tight text-primary sm:text-3xl">ログイン</h1>

@if ($errors->any())
    <x-alert variant="error" class="mb-6" :messages="$errors->all()" />
@endif

<x-card class="p-8 shadow-lg">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf
        <x-form.field :show-error="false" name="email" label="メールアドレス" type="email" required />
        <x-form.field :show-error="false" name="password" label="パスワード" type="password" required />
        <x-button class="mt-2 w-full">ログイン</x-button>
    </form>
</x-card>

<p class="mt-6 text-center text-sm text-on-surface-variant">
    <a href="{{ route('register') }}" class="font-semibold text-primary underline-offset-4 transition hover:text-secondary hover:underline">アカウントがない方はこちら</a>
</p>
@endsection
