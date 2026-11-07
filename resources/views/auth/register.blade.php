@extends('layouts.auth')
@section('title', 'ユーザー新規登録')
@section('content')
<h1 class="mb-6 text-center font-headline text-2xl font-semibold tracking-tight text-primary sm:text-3xl">ユーザー新規登録</h1>

@if ($errors->any())
    <x-alert variant="error" class="mb-6" :messages="$errors->all()" />
@endif

<x-card class="p-8 shadow-lg">
    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
        @csrf
        <x-form.field :show-error="false" name="name" label="ユーザーネーム" type="text" required />
        <x-form.field :show-error="false" name="email" label="メールアドレス" type="text" required />
        <x-form.field :show-error="false" name="date_of_birth" label="生年月日" type="text" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}" required />
        <x-form.field :show-error="false" name="password" label="パスワード" type="password" required />
        <x-form.field :show-error="false" name="password_confirmation" label="パスワード（確認用）" type="password" required />
        <x-button class="mt-2 w-full">新規登録</x-button>
    </form>
</x-card>

<p class="mt-6 text-center text-sm text-on-surface-variant">
    <a href="{{ route('login') }}" class="font-semibold text-primary underline-offset-4 transition hover:text-secondary hover:underline">アカウントお持ちの方はこちら</a>
</p>
@endsection
