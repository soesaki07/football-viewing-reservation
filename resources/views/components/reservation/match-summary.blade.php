{{-- 試合・店舗の概要（予約の各画面で共通）。footballMatch：試合（ホーム・アウェイのチームを読み込み済み）、shop：店舗、showAddress：true で店舗の住所も出す --}}
@props(['footballMatch', 'shop', 'showAddress' => false])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl bg-surface-container-low p-4 sm:p-5']) }}>
    <p class="font-headline text-lg font-semibold tracking-tight text-primary sm:text-xl">{{ $footballMatch->homeTeam->displayName() }} vs {{ $footballMatch->awayTeam->displayName() }}</p>
    <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
        <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">キックオフ（日本時間）</dt>
        <dd class="text-on-surface">{{ $footballMatch->kickoff_at->copy()->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
        <dt class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">店舗</dt>
        <dd class="text-on-surface">
            {{ $shop->name }}
            @if ($showAddress)
                <span class="block text-xs text-on-surface-variant">{{ $shop->fullAddress() }}</span>
            @endif
        </dd>
    </dl>
</div>
