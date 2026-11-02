@extends('layouts.app')
@section('title', '店舗情報を編集')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">🍺</span>
        店舗情報を編集
    </h1>

    {{-- 公開中の店舗を非公開にして保存するときだけ、確認ダイアログを出す（画面を開いたときの公開状態で決める） --}}
    <form method="POST" action="{{ route('owner.shops.update', $shop) }}"
        @if ($shop->isPublished()) data-confirm="この店舗を非公開にしますか？店舗の詳細ページが見られなくなり、新しい予約ができなくなります（すでに入っている予約はそのまま有効です）。" data-confirm-field="status" data-confirm-value="{{ \App\Models\Shop::STATUS_DRAFT }}" @endif
        class="space-y-6 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
        @csrf
        @method('PUT')

        @include('owner.shops._form')

        <div>
            <label for="status" class="block text-sm font-semibold text-pitch-500">公開状態</label>
            <select id="status" name="status" required class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                <option value="{{ \App\Models\Shop::STATUS_DRAFT }}" @selected(old('status', $shop->status) === \App\Models\Shop::STATUS_DRAFT)>非公開</option>
                <option value="{{ \App\Models\Shop::STATUS_PUBLISHED }}" @selected(old('status', $shop->status) === \App\Models\Shop::STATUS_PUBLISHED)>公開</option>
            </select>
            @error('status')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-pitch-500">非公開にすると、店舗の詳細ページが見られなくなり、新しい予約ができなくなります。すでに入っている予約はそのまま有効です。</p>
        </div>

        <p class="text-xs text-pitch-500">住所（郵便番号・都道府県・市区町村・番地以降）を変更すると、登録済みの位置情報（緯度経度）は消え、地図は住所から表示されます。</p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-pitch-800 px-6 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">更新する</button>
            <a href="{{ route('owner.shops.index') }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">一覧に戻る</a>
        </div>
    </form>
</div>
@endsection
