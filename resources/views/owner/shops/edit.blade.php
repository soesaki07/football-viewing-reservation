@extends('layouts.owner')
@section('title', '店舗情報を編集')
@section('content')
<div class="space-y-6">
    <nav aria-label="パンくず" class="text-xs font-semibold text-on-surface-variant">
        <a href="{{ route('owner.shops.index') }}" class="hover:text-primary">店舗情報</a>
        <span aria-hidden="true">/</span>
        <span class="text-on-surface">店舗情報を編集</span>
    </nav>

    <x-page-header title="店舗情報を編集" eyebrow="Partner Console" />

    {{-- 公開中の店舗を非公開にして保存するときだけ、確認ダイアログを出す（画面を開いたときの公開状態で決める） --}}
    <form method="POST" action="{{ route('owner.shops.update', $shop) }}"
        @if ($shop->isPublished()) data-confirm="この店舗を非公開にしますか？店舗の詳細ページが見られなくなり、新しい予約ができなくなります（すでに入っている予約はそのまま有効です）。" data-confirm-field="status" data-confirm-value="{{ \App\Models\Shop::STATUS_DRAFT }}" @endif>
        @csrf
        @method('PUT')

        <x-card class="space-y-8">
            @include('owner.shops._form')

            <x-owner.form-section title="公開状態">
                <x-form.field name="status" type="select" label="公開状態" required
                    hint="非公開にすると、店舗の詳細ページが見られなくなり、新しい予約ができなくなります。すでに入っている予約はそのまま有効です。">
                    <option value="{{ \App\Models\Shop::STATUS_DRAFT }}" @selected(old('status', $shop->status) === \App\Models\Shop::STATUS_DRAFT)>非公開</option>
                    <option value="{{ \App\Models\Shop::STATUS_PUBLISHED }}" @selected(old('status', $shop->status) === \App\Models\Shop::STATUS_PUBLISHED)>公開</option>
                </x-form.field>
            </x-owner.form-section>

            <p class="text-xs text-on-surface-variant">住所（郵便番号・都道府県・市区町村・番地以降）を変更すると、登録済みの位置情報（緯度経度）は消え、地図は住所から表示されます。</p>

            <div class="flex flex-wrap items-center gap-3">
                <x-button>更新する</x-button>
                <x-button variant="secondary" :href="route('owner.shops.index')">一覧に戻る</x-button>
            </div>
        </x-card>
    </form>
</div>
@endsection
