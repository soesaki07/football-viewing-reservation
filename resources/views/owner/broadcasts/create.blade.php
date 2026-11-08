@extends('layouts.owner')
@section('title', '放映を登録')
@if (! $shops->isEmpty())
@section('shop-switcher')
<x-owner.shop-switcher :shops="$shops" :current="$shop" />
@endsection
@endif
@section('content')
<div class="space-y-6">
    @if ($shops->isEmpty())
        <x-page-header title="放映を登録" eyebrow="Partner Console" />
        <x-card class="text-sm text-on-surface-variant">店舗が登録されていません。</x-card>
    @else
        <nav aria-label="パンくず" class="text-xs font-semibold text-on-surface-variant">
            <a href="{{ route('owner.broadcasts.index', ['shop' => $shop->id]) }}" class="hover:text-primary">放映管理</a>
            <span aria-hidden="true">/</span>
            <span class="text-on-surface">放映を登録</span>
        </nav>

        <x-page-header title="放映を登録" eyebrow="Partner Console" />

        {{-- 試合のエラー（試合は画面上の入力欄がないので、ここで理由を見せる） --}}
        @if ($errors->has('football_match_id'))
            <x-alert variant="error" :messages="$errors->get('football_match_id')" />
        @endif

        <p class="text-sm font-semibold text-on-surface-variant">店舗：<span class="text-on-surface">{{ $shop->name }}</span></p>

        @if ($seatTypes->isEmpty())
            <x-card class="text-sm text-on-surface-variant">有効な座席種別がないため、放映を登録できません。先に<a href="{{ route('owner.seat-types.create', ['shop' => $shop->id]) }}" class="font-bold text-primary underline hover:text-secondary">座席種別を登録</a>してください。</x-card>
        @else
            {{-- 1. 大会と試合を選ぶ --}}
            <form method="GET" action="{{ route('owner.broadcasts.create') }}">
                <input type="hidden" name="shop" value="{{ $shop->id }}">
                <x-card class="space-y-4">
                    <x-owner.form-section title="大会と試合を選ぶ">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="min-w-0 flex-1">
                                <x-form.field name="competition" type="select" label="大会">
                                    <option value="">大会を選択してください</option>
                                    @foreach ($competitions as $competition)
                                        <option value="{{ $competition->id }}" @selected($competitionId === $competition->id)>{{ $competition->displayName() }}</option>
                                    @endforeach
                                </x-form.field>
                            </div>
                            <x-button variant="secondary">大会を選ぶ</x-button>
                        </div>

                        @if ($competitionId !== null)
                            @if ($matchOptions->isEmpty())
                                <p class="text-sm text-on-surface-variant">登録できる試合がありません。</p>
                            @else
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div class="min-w-0 flex-1">
                                        <x-form.field name="match" type="select" label="試合">
                                            <option value="">試合を選択してください</option>
                                            @foreach ($matchOptions as $matchOption)
                                                <option value="{{ $matchOption->id }}" @selected($selectedMatch?->id === $matchOption->id)>
                                                    {{ $matchOption->homeTeam->displayName() }} vs {{ $matchOption->awayTeam->displayName() }}（{{ $matchOption->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）
                                                </option>
                                            @endforeach
                                        </x-form.field>
                                    </div>
                                    <x-button>この試合で入力する</x-button>
                                </div>
                            @endif
                        @endif
                    </x-owner.form-section>
                </x-card>
            </form>

            {{-- 2. 受付期間・座席を入力する --}}
            @if ($selectedMatch !== null)
                <form method="POST" action="{{ route('owner.broadcasts.store') }}">
                    @csrf
                    <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                    <input type="hidden" name="football_match_id" value="{{ $selectedMatch->id }}">

                    <x-card class="space-y-8">
                        <x-owner.form-section title="試合と受付期間">
                            <p class="text-sm font-semibold text-on-surface-variant">試合：<span class="text-on-surface">{{ $selectedMatch->homeTeam->displayName() }} vs {{ $selectedMatch->awayTeam->displayName() }}（{{ $selectedMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）</span></p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-form.field name="reservation_start_at" type="datetime-local" label="予約開始日時（日本時間）" required
                                    :value="now()->timezone('Asia/Tokyo')->startOfMinute()->format('Y-m-d\TH:i')" />
                                <x-form.field name="reservation_end_at" type="datetime-local" label="予約終了日時（日本時間）" required
                                    :value="$selectedMatch->kickoff_at->copy()->addHours(3)->timezone('Asia/Tokyo')->format('Y-m-d\TH:i')"
                                    hint="キックオフの3時間後（試合終了）まで設定できます。" />
                            </div>

                            <x-form.field name="status" type="select" label="放映ステータス">
                                <option value="{{ \App\Models\Broadcast::STATUS_DRAFT }}" @selected(old('status', \App\Models\Broadcast::STATUS_DRAFT) === \App\Models\Broadcast::STATUS_DRAFT)>非公開</option>
                                <option value="{{ \App\Models\Broadcast::STATUS_PUBLISHED }}" @selected(old('status', \App\Models\Broadcast::STATUS_DRAFT) === \App\Models\Broadcast::STATUS_PUBLISHED)>公開</option>
                            </x-form.field>
                        </x-owner.form-section>

                        <fieldset class="space-y-4">
                            <legend class="w-full border-b border-outline-variant/60 pb-2 font-headline text-lg font-semibold text-primary">販売する座席</legend>
                            @error('seats')
                                <p class="text-sm font-bold text-error">{{ $message }}</p>
                            @enderror
                            @php($oldSeats = old('seats'))
                            @foreach ($seatTypes as $seatType)
                                {{-- 入力値には、チェックを外した座席の席数なども含まれるため、selected の有無で判定する --}}
                                @php($isChecked = ! empty($oldSeats[$seatType->id]['selected']))
                                <div class="rounded-xl bg-surface-container-low p-4">
                                    <label class="flex items-center gap-2 text-sm font-bold text-on-surface">
                                        <input type="checkbox" name="seats[{{ $seatType->id }}][selected]" value="1" @checked($isChecked) class="h-4 w-4 accent-primary-container">
                                        {{ $seatType->name }}
                                    </label>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                        <x-form.field :name="'seats['.$seatType->id.'][capacity]'" type="number" label="販売席数" :value="$seatType->default_capacity" min="1" max="65535" />
                                        <x-form.field :name="'seats['.$seatType->id.'][price]'" type="number" label="料金（円）" :value="$seatType->default_price" min="0" max="1000000" />
                                        <x-form.field :name="'seats['.$seatType->id.'][max_people_per_reservation]'" type="number" label="1予約の最大人数" :value="4" min="1" max="65535" />
                                    </div>
                                </div>
                            @endforeach
                        </fieldset>

                        <div class="flex flex-wrap items-center gap-3">
                            <x-button>登録する</x-button>
                            <x-button variant="secondary" :href="route('owner.broadcasts.index', ['shop' => $shop->id])">一覧に戻る</x-button>
                        </div>
                    </x-card>
                </form>
            @endif
        @endif
    @endif
</div>
@endsection
