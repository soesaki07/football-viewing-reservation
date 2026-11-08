@extends('layouts.owner')
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
    <nav aria-label="パンくず" class="text-xs font-semibold text-on-surface-variant">
        <a href="{{ route('owner.broadcasts.index', ['shop' => $shop->id]) }}" class="hover:text-primary">放映管理</a>
        <span aria-hidden="true">/</span>
        <span class="text-on-surface">放映を編集</span>
    </nav>

    <x-page-header title="放映を編集" eyebrow="Partner Console" />

    <p class="text-sm font-semibold text-on-surface-variant">店舗：<span class="text-on-surface">{{ $shop->name }}</span></p>

    <form method="POST" action="{{ route('owner.broadcasts.update', $broadcast) }}">
        @csrf
        @method('PUT')

        <x-card class="space-y-8">
            <x-owner.form-section title="試合と受付期間">
                <p class="text-sm font-semibold text-on-surface-variant">試合：<span class="text-on-surface">{{ $match->homeTeam->displayName() }} vs {{ $match->awayTeam->displayName() }}（{{ $match->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}）</span></p>
                <p class="text-xs text-on-surface-variant">試合は変更できません。</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.field name="reservation_start_at" type="datetime-local" label="予約開始日時（日本時間）" required
                        :value="$toJst($broadcast->reservation_start_at)" />
                    <x-form.field name="reservation_end_at" type="datetime-local" label="予約終了日時（日本時間）" required
                        :value="$toJst($broadcast->reservation_end_at)"
                        hint="キックオフの3時間後（試合終了）まで設定できます。" />
                </div>

                <x-form.field name="status" type="select" label="放映ステータス" hint="非公開にしても、すでに入っている予約はそのまま残ります。">
                    <option value="{{ \App\Models\Broadcast::STATUS_DRAFT }}" @selected(old('status', $broadcast->status) === \App\Models\Broadcast::STATUS_DRAFT)>非公開</option>
                    <option value="{{ \App\Models\Broadcast::STATUS_PUBLISHED }}" @selected(old('status', $broadcast->status) === \App\Models\Broadcast::STATUS_PUBLISHED)>公開</option>
                </x-form.field>
            </x-owner.form-section>

            <fieldset class="space-y-4">
                <legend class="w-full border-b border-outline-variant/60 pb-2 font-headline text-lg font-semibold text-primary">販売する座席</legend>
                <p class="text-xs text-on-surface-variant">チェックを外した座席は販売停止になります（すでに入っている予約はそのまま有効です）。1つ以上は販売してください。</p>
                @error('seats')
                    <p class="text-sm font-bold text-error">{{ $message }}</p>
                @enderror
                @foreach ($rows as $row)
                    {{-- 入力値には、チェックを外した座席の席数なども含まれるため、selected の有無で判定する。入力値がないときは今の状態 --}}
                    @php($isChecked = $oldSeats !== null ? ! empty($oldSeats[$row['id']]['selected']) : $row['defaultChecked'])
                    <div class="rounded-xl bg-surface-container-low p-4">
                        <label class="flex flex-wrap items-center gap-x-2 text-sm font-bold text-on-surface">
                            <input type="checkbox" name="seats[{{ $row['id'] }}][selected]" value="1" @checked($isChecked) @disabled(! $row['canCheck']) class="h-4 w-4 accent-primary-container">
                            {{ $row['name'] }}
                            @if ($row['isExisting'] && ! $row['isSeatTypeActive'] && $row['defaultChecked'])
                                <span class="text-xs font-semibold text-error">（座席種別が無効のため、販売停止にすると再開できません）</span>
                            @elseif ($row['isExisting'] && ! $row['isSeatTypeActive'])
                                <span class="text-xs font-semibold text-error">（座席種別が無効のため、販売を再開できません）</span>
                            @elseif ($row['isExisting'] && ! $row['defaultChecked'])
                                <span class="text-xs font-semibold text-on-surface-variant">（販売停止中）</span>
                            @elseif (! $row['isExisting'])
                                <span class="text-xs font-semibold text-on-surface-variant">（追加できる座席）</span>
                            @endif
                        </label>
                        @if ($row['isExisting'])
                            <p class="mt-1 text-xs text-on-surface-variant">予約済み（来店済みを含む）：{{ $row['reserved'] }}人（販売席数はこれより少なくできません）</p>
                        @endif
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <x-form.field :name="'seats['.$row['id'].'][capacity]'" type="number" label="販売席数" :value="$row['capacity']" min="{{ max(1, $row['reserved']) }}" max="65535" />
                            <x-form.field :name="'seats['.$row['id'].'][price]'" type="number" label="料金（円）" :value="$row['price']" min="0" max="1000000" />
                            <x-form.field :name="'seats['.$row['id'].'][max_people_per_reservation]'" type="number" label="1予約の最大人数" :value="$row['max']" min="1" max="65535" />
                        </div>
                        @if ($row['isExisting'])
                            <p class="mt-2 text-xs text-on-surface-variant">料金を変えても、すでに入っている予約の金額は変わりません。</p>
                        @endif
                    </div>
                @endforeach
            </fieldset>

            <div class="flex flex-wrap items-center gap-3">
                <x-button>更新する</x-button>
                <x-button variant="secondary" :href="route('owner.broadcasts.index', ['shop' => $shop->id])">一覧に戻る</x-button>
            </div>
        </x-card>
    </form>
</div>
@endsection
