@extends('layouts.app')
@section('title', 'お気に入りチーム登録')
@section('content')
<div class="mx-auto max-w-4xl">
    <x-page-header eyebrow="Favorite Clubs" title="お気に入りチーム登録" description="最大３チームまで" />

    @if ($errors->any())
        <x-alert variant="error" class="mb-6" :messages="$errors->all()" />
    @endif

    <div id="selected-team-names" class="mb-4 flex min-h-8 flex-wrap items-center gap-2"></div>

    <label class="mb-6 flex flex-col gap-1.5 rounded-xl bg-primary-container p-4 text-[11px] font-bold uppercase tracking-wider text-primary-fixed shadow-md sm:p-5">
        大会選択
        <select name="competition" id="competition_id" onchange="filterTeams(this.value)"
                class="w-full min-w-0 rounded-lg border-0 bg-surface-container-lowest px-3 py-2.5 text-sm font-medium normal-case tracking-normal text-on-surface focus:outline-none focus:ring-2 focus:ring-secondary-container">
            @foreach ($competitions as $competition)
                <option
                    value="{{ $competition->code }}" @selected($competition->code === $competitionCode)>
                    {{ $competition->displayName() }}
                </option>
            @endforeach
        </select>
    </label>

    @if ($teams->isNotEmpty())
    <form id="favorite-teams-form" method="POST" action="{{ route('save.teams') }}" class="flex flex-col gap-6">
        @method('PUT')
        @csrf

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teams as $team)
                <label class="team-card flex cursor-pointer items-center gap-3 rounded-xl bg-surface-container-lowest p-4 shadow-sm ring-1 ring-outline-variant/40 transition hover:-translate-y-0.5 hover:shadow-md has-[:checked]:bg-secondary-fixed has-[:checked]:ring-2 has-[:checked]:ring-secondary has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-secondary"
                       data-competitions="{{ implode(' ', $teamCompetitionCodes[$team->id] ?? []) }}">
                    <input type="checkbox" name="team_ids[]" value="{{ $team->id }}"
                           @checked($favoriteTeamIds->contains($team->id))
                           class="h-5 w-5 shrink-0 accent-secondary">
                    @if ($team->crest_url)
                        <img src="{{ $team->crest_url }}" alt="" class="h-10 w-10 shrink-0 object-contain">
                    @endif
                    <span class="min-w-0 flex-1">
                        <span class="team-name block truncate text-sm font-bold text-primary">{{ $team->displayShortName() }}</span>
                        @if ($team->name_ja)
                            <span class="block truncate text-xs text-on-surface-variant">{{ $team->name }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </form>
    @else
        <p class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-10 text-center text-sm text-on-surface-variant">
            該当するチームが見つかりませんでした。
        </p>
    @endif
    <div class="mt-6 flex items-center justify-between">
        <a href="{{ route('matches.index') }}" class="text-sm font-semibold text-on-surface-variant underline transition hover:text-primary">スキップ</a>
        @if ($teams->isNotEmpty())
            <x-button type="submit" form="favorite-teams-form">登録</x-button>
        @endif
    </div>
</div>

<script>
function filterTeams(selectedCode) {
    document.querySelectorAll('.team-card').forEach(function (card) {
        const codes = card.dataset.competitions.split(' ');
        card.style.display = codes.includes(selectedCode) ? '' : 'none';
    });
}

function updateSelectedTeamNames() {
    const names = Array.from(document.querySelectorAll('.team-card input[type="checkbox"]:checked'))
        .map(function (checkbox) {
            return checkbox.closest('.team-card').querySelector('.team-name').textContent.trim();
        });

    const container = document.getElementById('selected-team-names');
    container.innerHTML = '';

    if (names.length === 0) {
        return;
    }

    const label = document.createElement('span');
    label.className = 'text-sm font-semibold text-on-surface-variant';
    label.textContent = '選択中（' + names.length + '/3）:';
    container.appendChild(label);

    names.forEach(function (name) {
        const pill = document.createElement('span');
        pill.className = 'rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary';
        pill.textContent = name;
        container.appendChild(pill);
    });
}

document.querySelectorAll('.team-card input[type="checkbox"]').forEach(function (checkbox) {
    checkbox.addEventListener('change', updateSelectedTeamNames);
});

filterTeams(document.getElementById('competition_id').value);
updateSelectedTeamNames();
</script>
@endsection
