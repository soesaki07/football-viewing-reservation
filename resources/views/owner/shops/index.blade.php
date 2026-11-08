@extends('layouts.owner')
@section('title', '店舗情報')
@section('content')
<div class="space-y-6">
    <x-page-header title="店舗情報" eyebrow="Partner Console">
        <x-button :href="route('owner.shops.create')">店舗を登録する</x-button>
    </x-page-header>

    @if (session('success'))
        <x-alert variant="success">{{ session('success') }}</x-alert>
    @endif

    @if ($shops->isEmpty())
        <x-card class="text-sm text-on-surface-variant">
            店舗がありません。<a href="{{ route('owner.shops.create') }}" class="font-bold text-primary underline hover:text-secondary">店舗を登録する</a>
        </x-card>
    @else
        <x-owner.table>
            <thead class="bg-surface-container-low text-xs font-semibold text-on-surface-variant">
                <tr>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">店名</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">住所</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">電話番号</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">営業時間</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">公開状態</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3"><span class="sr-only">操作</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/40">
                @foreach ($shops as $listedShop)
                    <tr>
                        <td class="min-w-32 px-4 py-3 font-bold text-on-surface">{{ $listedShop->name }}</td>
                        <td class="min-w-48 px-4 py-3 text-xs">
                            @if ($listedShop->postal_code)
                                〒{{ $listedShop->postal_code }}<br>
                            @endif
                            {{ $listedShop->fullAddress() }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $listedShop->phone_number ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $listedShop->businessHoursLabel() ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><x-badge :variant="$listedShop->isPublished() ? 'success' : 'neutral'">{{ $listedShop->isPublished() ? '公開' : '非公開' }}</x-badge></td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <a href="{{ route('owner.shops.edit', $listedShop) }}" class="text-sm font-bold text-primary hover:text-secondary">編集</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-owner.table>
    @endif
</div>
@endsection
