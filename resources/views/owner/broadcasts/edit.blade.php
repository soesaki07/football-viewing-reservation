@extends('layouts.app')
@section('title', '放映を編集')
@section('content')
@php
    $match = $broadcast->footballMatch;
    $toJst = fn ($value) => $value !== null ? \Illuminate\Support\Carbon::parse($value)->timezone('Asia/Tokyo')->format('Y-m-d\TH:i') : '';
    $oldSeats = old('seats');
    // 座席の入力欄。この放映にある座席（販売停止中も含む）→ まだ足していない座席種別の順
    $rows = [];
    foreach ($seats as $seat) {
        $isSeatTypeActive = (bool) $seat->seatType->is_active;
        $rows[] = [
            'id' => $seat->seat_type_id,
            'name' => $seat->seatType->name,
            'capacity' => $seat->capacity,
            'price' => $seat->price,
            'max' => $seat->max_people_per_reservation,
            'defaultChecked' => (bool) $seat->is_active,
            'reserved' => (int) $seat->occupied_people,
            'isExisting' => true,
            // 座席種別が無効のとき、販売中に戻すことはできない（販売中のまま保つのと、販売停止にするのは可）
            'canCheck' => $isSeatTypeActive || $seat->is_active,
            'isSeatTypeActive' => $isSeatTypeActive,
        ];
    }
    foreach ($addableSeatTypes as $seatType) {
        $rows[] = [
            'id' => $seatType->id,
            'name' => $seatType->name,
            'capacity' => $seatType->default_capacity,
            'price' => $seatType->default_price,
            'max' => 4,
            'defaultChecked' => false,
            'reserved' => 0,
            'isExisting' => false,
            'canCheck' => true,
            'isSeatTypeActive' => true,
        ];
    }
@endphp
<div class="space-y-6">
    <h1 class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">📺</span>
        放映を編集
    </h1>

    <p class="text-sm font-semibold text-pitch-500">店舗：<span class="text-pitch-950">{{ $shop->name }}</span></p>

    <form method="POST" action="{{ route('owner.broadcasts.update', $broadcast) }}" class="space-y-6 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm">
        @csrf
        @method('PUT')

        <p class="text-sm font-semibold text-pitch-500">試合：<span class="text-pitch-950">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}（{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）</span></p>
        <p class="text-xs text-pitch-500">試合は変更できません。</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="reservation_start_at" class="block text-sm font-semibold text-pitch-500">予約開始日時（日本時間）</label>
                <input type="datetime-local" id="reservation_start_at" name="reservation_start_at" required
                    value="{{ old('reservation_start_at', $toJst($broadcast->reservation_start_at)) }}"
                    class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                @error('reservation_start_at')
                    <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="reservation_end_at" class="block text-sm font-semibold text-pitch-500">予約終了日時（日本時間）</label>
                <input type="datetime-local" id="reservation_end_at" name="reservation_end_at" required
                    value="{{ old('reservation_end_at', $toJst($broadcast->reservation_end_at)) }}"
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
                <option value="{{ \App\Models\Broadcast::STATUS_DRAFT }}" @selected(old('status', $broadcast->status) === \App\Models\Broadcast::STATUS_DRAFT)>非公開</option>
                <option value="{{ \App\Models\Broadcast::STATUS_PUBLISHED }}" @selected(old('status', $broadcast->status) === \App\Models\Broadcast::STATUS_PUBLISHED)>公開</option>
            </select>
            @error('status')
                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-pitch-500">非公開にしても、すでに入っている予約はそのまま残ります。</p>
        </div>

        <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-pitch-500">販売する座席</legend>
            <p class="text-xs text-pitch-500">チェックを外した座席は販売停止になります（すでに入っている予約はそのまま有効です）。1つ以上は販売してください。</p>
            @error('seats')
                <p class="text-sm font-bold text-red-700">{{ $message }}</p>
            @enderror
            @foreach ($rows as $row)
                {{-- 入力値には、チェックを外した座席の席数なども含まれるため、selected の有無で判定する。入力値がないときは今の状態 --}}
                @php($isChecked = $oldSeats !== null ? ! empty($oldSeats[$row['id']]['selected']) : $row['defaultChecked'])
                <div class="rounded-lg border border-pitch-100 p-3">
                    <label class="flex items-center gap-2 text-sm font-bold text-pitch-950">
                        <input type="checkbox" name="seats[{{ $row['id'] }}][selected]" value="1" @checked($isChecked) @disabled(! $row['canCheck'])>
                        {{ $row['name'] }}
                        @if ($row['isExisting'] && ! $row['isSeatTypeActive'] && $row['defaultChecked'])
                            <span class="text-xs font-semibold text-red-700">（座席種別が無効のため、販売停止にすると再開できません）</span>
                        @elseif ($row['isExisting'] && ! $row['isSeatTypeActive'])
                            <span class="text-xs font-semibold text-red-700">（座席種別が無効のため、販売を再開できません）</span>
                        @elseif ($row['isExisting'] && ! $row['defaultChecked'])
                            <span class="text-xs font-semibold text-pitch-500">（販売停止中）</span>
                        @elseif (! $row['isExisting'])
                            <span class="text-xs font-semibold text-pitch-500">（追加できる座席）</span>
                        @endif
                    </label>
                    @if ($row['isExisting'])
                        <p class="mt-1 text-xs text-pitch-500">予約済み（来店済みを含む）：{{ $row['reserved'] }}人（販売席数はこれより少なくできません）</p>
                    @endif
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="seat_{{ $row['id'] }}_capacity" class="block text-xs font-semibold text-pitch-500">販売席数</label>
                            <input type="number" id="seat_{{ $row['id'] }}_capacity" name="seats[{{ $row['id'] }}][capacity]" min="{{ max(1, $row['reserved']) }}" max="65535"
                                value="{{ old("seats.{$row['id']}.capacity", $row['capacity']) }}"
                                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                            @error("seats.{$row['id']}.capacity")
                                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="seat_{{ $row['id'] }}_price" class="block text-xs font-semibold text-pitch-500">料金（円）</label>
                            <input type="number" id="seat_{{ $row['id'] }}_price" name="seats[{{ $row['id'] }}][price]" min="0" max="1000000"
                                value="{{ old("seats.{$row['id']}.price", $row['price']) }}"
                                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                            @error("seats.{$row['id']}.price")
                                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="seat_{{ $row['id'] }}_max" class="block text-xs font-semibold text-pitch-500">1予約の最大人数</label>
                            <input type="number" id="seat_{{ $row['id'] }}_max" name="seats[{{ $row['id'] }}][max_people_per_reservation]" min="1" max="65535"
                                value="{{ old("seats.{$row['id']}.max_people_per_reservation", $row['max']) }}"
                                class="mt-1 w-full rounded-md border border-pitch-100 bg-white px-3 py-2 text-sm text-pitch-950">
                            @error("seats.{$row['id']}.max_people_per_reservation")
                                <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    @if ($row['isExisting'])
                        <p class="mt-2 text-xs text-pitch-500">料金を変えても、すでに入っている予約の金額は変わりません。</p>
                    @endif
                </div>
            @endforeach
        </fieldset>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-pitch-800 px-6 py-2 text-sm font-bold text-white transition hover:bg-pitch-700">更新する</button>
            <a href="{{ route('owner.broadcasts.index', ['shop' => $shop->id]) }}" class="text-sm font-bold text-pitch-700 hover:text-gold-500">一覧に戻る</a>
        </div>
    </form>
</div>
@endsection
