@extends('layouts.app')
@section('title', '放映を登録')
@section('content')
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">📺</span>
        放映を登録
    </h1>

    @if ($shops->isEmpty())
        <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">店舗が登録されていません。</p>
    @else
        {{-- 試合のエラー（試合は画面上の入力欄がないので、ここで理由を見せる） --}}
        @if ($errors->has('football_match_id'))
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700" role="alert">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->get('football_match_id') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 店舗の切り替え（2店舗以上のときだけ） --}}
        @if ($shops->count() >= 2)
            <nav class="flex flex-wrap gap-2" aria-label="店舗の切り替え">
                @foreach ($shops as $ownedShop)
                    <a href="{{ route('owner.broadcasts.create', ['shop' => $ownedShop->id]) }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm font-bold transition',
                            'bg-pitch-800 text-white' => $ownedShop->id === $shop->id,
                            'border border-pitch-100 bg-white text-pitch-700 hover:text-gold-500' => $ownedShop->id !== $shop->id,
                        ])
                        @if ($ownedShop->id === $shop->id) aria-current="page" @endif>{{ $ownedShop->name }}</a>
                @endforeach
            </nav>
        @endif

        <p class="text-sm font-semibold text-pitch-500">店舗：<span class="text-pitch-950">{{ $shop->name }}</span></p>

        @if ($seatTypes->isEmpty())
            <p class="rounded-xl border border-pitch-100 bg-white p-6 text-sm text-pitch-700">有効な座席種別がないため、放映を登録できません。先に座席種別を登録してください。</p>
        @else
            {{-- 1. 大会と試合を選ぶ --}}
            <form method="GET" action="{{ route('owner.broadcasts.create') }}" class="space-y-3 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
                <input type="hidden" name="shop" value="{{ $shop->id }}">
                <div class="flex flex-wrap items-center gap-3">
                    <label for="competition" class="text-sm font-semibold text-pitch-500">大会</label>
                    <select id="competition" name="competition" class="max-w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                        <option value="">大会を選択してください</option>
                        @foreach ($competitions as $competition)
                            <option value="{{ $competition->id }}" @selected($competitionId === $competition->id)>{{ $competition->displayName() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md border border-pitch-100 bg-white px-4 py-2 text-sm font-bold text-pitch-700 transition hover:text-gold-500">大会を選ぶ</button>
                </div>

                @if ($competitionId !== null)
                    <div class="flex flex-wrap items-center gap-3">
                        <label for="match" class="text-sm font-semibold text-pitch-500">試合</label>
                        @if ($matchOptions->isEmpty())
                            <p class="text-sm text-pitch-700">登録できる試合がありません。</p>
                        @else
                            <select id="match" name="match" class="max-w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                                <option value="">試合を選択してください</option>
                                @foreach ($matchOptions as $matchOption)
                                    <option value="{{ $matchOption->id }}" @selected($selectedMatch?->id === $matchOption->id)>
                                        {{ $matchOption->homeTeam->displayName() }} vs {{ $matchOption->awayTeam->displayName() }}（{{ $matchOption->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-pitch-800 px-4 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">この試合で入力する</button>
                        @endif
                    </div>
                @endif
            </form>

            {{-- 2. 受付期間・座席を入力する --}}
            @if ($selectedMatch !== null)
                <form method="POST" action="{{ route('owner.broadcasts.store') }}" class="space-y-6 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
                    @csrf
                    <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                    <input type="hidden" name="football_match_id" value="{{ $selectedMatch->id }}">

                    <p class="text-sm font-semibold text-pitch-500">試合：<span class="text-pitch-950">{{ $selectedMatch->homeTeam->displayName() }} vs {{ $selectedMatch->awayTeam->displayName() }}（{{ $selectedMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）</span></p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="reservation_start_at" class="block text-sm font-semibold text-pitch-500">予約開始日時（日本時間）</label>
                            <input type="datetime-local" id="reservation_start_at" name="reservation_start_at" required
                                value="{{ old('reservation_start_at', now()->timezone('Asia/Tokyo')->startOfMinute()->format('Y-m-d\TH:i')) }}"
                                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                            @error('reservation_start_at')
                                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="reservation_end_at" class="block text-sm font-semibold text-pitch-500">予約終了日時（日本時間）</label>
                            <input type="datetime-local" id="reservation_end_at" name="reservation_end_at" required
                                value="{{ old('reservation_end_at', $selectedMatch->kickoff_at->copy()->addHours(3)->timezone('Asia/Tokyo')->format('Y-m-d\TH:i')) }}"
                                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                            @error('reservation_end_at')
                                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-pitch-500">キックオフの3時間後（試合終了）まで設定できます。</p>
                        </div>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-semibold text-pitch-500">放映ステータス</label>
                        <select id="status" name="status" class="mt-1 rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                            <option value="draft" @selected(old('status', 'draft') === 'draft')>非公開</option>
                            <option value="published" @selected(old('status', 'draft') === 'published')>公開</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-pitch-500">販売する座席</legend>
                        @error('seats')
                            <p class="text-sm font-bold text-red-700">{{ $message }}</p>
                        @enderror
                        @php($oldSeats = old('seats'))
                        @foreach ($seatTypes as $seatType)
                            {{-- 入力値には、チェックを外した座席の席数なども含まれるため、selected の有無で判定する --}}
                            @php($isChecked = ! empty($oldSeats[$seatType->id]['selected']))
                            <div class="rounded-lg border border-pitch-100 p-3">
                                <label class="flex items-center gap-2 text-sm font-bold text-pitch-950">
                                    <input type="checkbox" name="seats[{{ $seatType->id }}][selected]" value="1" @checked($isChecked)>
                                    {{ $seatType->name }}
                                </label>
                                <div class="mt-2 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label for="seat_{{ $seatType->id }}_capacity" class="block text-xs font-semibold text-pitch-500">販売席数</label>
                                        <input type="number" id="seat_{{ $seatType->id }}_capacity" name="seats[{{ $seatType->id }}][capacity]" min="1" max="65535"
                                            value="{{ old("seats.{$seatType->id}.capacity", $seatType->default_capacity) }}"
                                            class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                                        @error("seats.{$seatType->id}.capacity")
                                            <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="seat_{{ $seatType->id }}_price" class="block text-xs font-semibold text-pitch-500">料金（円）</label>
                                        <input type="number" id="seat_{{ $seatType->id }}_price" name="seats[{{ $seatType->id }}][price]" min="0" max="1000000"
                                            value="{{ old("seats.{$seatType->id}.price", $seatType->default_price) }}"
                                            class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                                        @error("seats.{$seatType->id}.price")
                                            <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="seat_{{ $seatType->id }}_max" class="block text-xs font-semibold text-pitch-500">1予約の最大人数</label>
                                        <input type="number" id="seat_{{ $seatType->id }}_max" name="seats[{{ $seatType->id }}][max_people_per_reservation]" min="1" max="65535"
                                            value="{{ old("seats.{$seatType->id}.max_people_per_reservation", 4) }}"
                                            class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                                        @error("seats.{$seatType->id}.max_people_per_reservation")
                                            <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="rounded-md bg-pitch-800 px-6 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">登録する</button>
                        <a href="{{ route('owner.broadcasts.index', ['shop' => $shop->id]) }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">一覧に戻る</a>
                    </div>
                </form>
            @endif
        @endif
    @endif
</div>
@endsection
