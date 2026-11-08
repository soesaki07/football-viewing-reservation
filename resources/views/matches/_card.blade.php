<a href="{{ route('matches.show', $footballMatch) }}" class="group flex flex-col gap-4 rounded-xl bg-surface-container-lowest p-5 shadow-sm ring-1 ring-outline-variant/40 transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary">
    <div class="flex items-center justify-between gap-3 text-base font-bold text-primary sm:text-lg">
        <span class="flex min-w-0 flex-1 items-center gap-2">
            @if ($footballMatch->homeTeam->crest_url)
                <img src="{{ $footballMatch->homeTeam->crest_url }}" alt="" class="h-7 w-7 shrink-0 object-contain">
            @endif
            <span class="truncate">{{ $footballMatch->homeTeam->tla ?? $footballMatch->homeTeam->displayShortName() }}</span>
        </span>

        @if (! is_null($footballMatch->home_score) && ! is_null($footballMatch->away_score))
            <span data-testid="match-score" class="shrink-0 rounded-lg bg-primary-container px-3 py-1 text-sm font-extrabold text-on-primary">
                {{ $footballMatch->home_score }} - {{ $footballMatch->away_score }}
            </span>
        @else
            <span data-testid="match-vs" class="shrink-0 rounded-lg bg-surface-container px-2.5 py-1 text-xs font-bold text-on-surface-variant">VS</span>
        @endif

        <span class="flex min-w-0 flex-1 items-center justify-end gap-2">
            <span class="truncate text-right">{{ $footballMatch->awayTeam->tla ?? $footballMatch->awayTeam->displayShortName() }}</span>
            @if ($footballMatch->awayTeam->crest_url)
                <img src="{{ $footballMatch->awayTeam->crest_url }}" alt="" class="h-7 w-7 shrink-0 object-contain">
            @endif
        </span>
    </div>

    <div class="flex items-center justify-between gap-2 border-t border-outline-variant/40 pt-3 text-sm font-medium text-on-surface-variant">
        <span>
            {{ $footballMatch->kickoff_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}
            <span class="text-outline">(JST)</span>
        </span>
        <x-match.status-badge :match="$footballMatch" class="shrink-0" />
    </div>
</a>
