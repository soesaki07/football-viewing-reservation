<a href="{{ route('matches.show', $footballMatch) }}" class="group relative overflow-hidden rounded-xl border border-pitch-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
    <span class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-pitch-600 to-gold-500"></span>

    <p class="mb-3 flex items-center justify-between gap-3 text-base font-bold text-pitch-950 sm:text-lg">
        <span class="flex min-w-0 items-center gap-2">
            @if ($footballMatch->homeTeam->crest_url)
                <img src="{{ $footballMatch->homeTeam->crest_url }}" alt="" class="h-6 w-6 shrink-0 object-contain">
            @endif
            <span class="truncate">{{ $footballMatch->homeTeam->tla ?? $footballMatch->homeTeam->short_name ?? $footballMatch->homeTeam->name }}</span>
        </span>

        @if (! is_null($footballMatch->home_score) && ! is_null($footballMatch->away_score))
            <span class="shrink-0 rounded-full bg-pitch-950 px-3 py-0.5 text-sm font-extrabold text-gold-400">
                {{ $footballMatch->home_score }} - {{ $footballMatch->away_score }}
            </span>
        @else
            <span class="shrink-0 rounded-full bg-pitch-950 px-2 py-0.5 text-xs font-semibold text-gold-400">VS</span>
        @endif

        <span class="flex min-w-0 items-center justify-end gap-2">
            <span class="truncate text-right">{{ $footballMatch->awayTeam->tla ?? $footballMatch->awayTeam->short_name ?? $footballMatch->awayTeam->name }}</span>
            @if ($footballMatch->awayTeam->crest_url)
                <img src="{{ $footballMatch->awayTeam->crest_url }}" alt="" class="h-6 w-6 shrink-0 object-contain">
            @endif
        </span>
    </p>

    <p class="flex items-center justify-between gap-2 text-sm font-medium text-pitch-700">
        <span>
            {{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}
            <span class="text-pitch-500">(JST)</span>
        </span>
        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold {{ $footballMatch->statusInfo()['class'] }}">
            {{ $footballMatch->statusInfo()['label'] }}
        </span>
    </p>
</a>
