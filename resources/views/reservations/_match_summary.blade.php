{{-- 試合・店舗の概要（予約の各画面で共通） --}}
<dl class="grid gap-3 text-sm text-pitch-700 sm:grid-cols-[8rem_1fr]">
    <dt class="font-semibold text-pitch-500">試合</dt>
    <dd class="font-bold text-pitch-950">{{ $footballMatch->homeTeam->displayName() }} vs {{ $footballMatch->awayTeam->displayName() }}</dd>
    <dt class="font-semibold text-pitch-500">キックオフ（日本時間）</dt>
    <dd>{{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</dd>
    <dt class="font-semibold text-pitch-500">店舗</dt>
    <dd>{{ $shop->name }}</dd>
</dl>
