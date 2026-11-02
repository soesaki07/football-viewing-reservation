@extends('layouts.app')
@section('title', '店舗情報')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">🍺</span>
        店舗情報
    </h1>

    @if (session('success'))
        <p class="rounded-xl border border-pitch-100 bg-pitch-50 p-4 text-sm font-bold text-pitch-950" role="status">{{ session('success') }}</p>
    @endif

    @if ($shops->isEmpty())
        <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">店舗がありません。</p>
    @else
        {{-- スマホ幅ではテーブルの中だけを横スクロールさせる --}}
        <div class="overflow-x-auto rounded-xl border border-pitch-100 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm text-pitch-700">
                <thead class="bg-pitch-50 text-xs font-semibold text-pitch-500">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">店名</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">住所</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">電話番号</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">営業時間</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">公開状態</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3"><span class="sr-only">操作</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pitch-100">
                    @foreach ($shops as $shop)
                        <tr>
                            <td class="min-w-32 px-4 py-3 font-bold text-pitch-950">{{ $shop->name }}</td>
                            <td class="min-w-48 px-4 py-3 text-xs">
                                @if ($shop->postal_code)
                                    〒{{ $shop->postal_code }}<br>
                                @endif
                                {{ $shop->fullAddress() }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $shop->phone_number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $shop->businessHoursLabel() ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-pitch-100 px-3 py-1 text-xs font-bold text-pitch-950">{{ $shop->isPublished() ? '公開' : '非公開' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <a href="{{ route('owner.shops.edit', $shop) }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">編集</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
