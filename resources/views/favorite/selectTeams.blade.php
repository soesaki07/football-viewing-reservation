@extends('layouts.app')
@section('title', 'お気に入りチーム登録')
@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="mb-2 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-pitch-950 sm:text-3xl">
        <span class="text-gold-500">⚽</span>
        お気に入りチーム登録
    </h1>
    <p class="mb-6 text-sm font-medium text-pitch-700">最大３チームまで</p>

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="selected-team-names" class="mb-4 flex min-h-8 flex-wrap items-center gap-2"></div>

    <label class="mb-6 flex flex-col gap-1 rounded-xl bg-pitch-950 p-4 text-sm font-semibold text-pitch-100 shadow-md sm:p-5">
        大会選択
        <select name="competition" id="competition_id" onchange="filterTeams(this.value)"
                class="rounded-lg border-0 bg-white px-3 py-2 text-sm font-medium text-pitch-950 shadow-inner focus:ring-2 focus:ring-gold-500 focus:outline-none">
            @foreach ($competitions as $competition)
                <option
                    value="{{ $competition->code }}" @selected($competition->code === $competitionCode)>
                    {{ $competition->name }}
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
                <label class="team-card flex cursor-pointer items-center gap-3 rounded-xl border border-pitch-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg has-[:checked]:border-gold-500 has-[:checked]:ring-2 has-[:checked]:ring-gold-500"
                       data-competitions="{{ implode(' ', $teamCompetitionCodes[$team->id] ?? []) }}">
                    <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" class="h-5 w-5 shrink-0 accent-gold-500">
                    <img src="{{ $team->crest_url }}" alt="" class="h-10 w-10 shrink-0 object-contain">
                    <span class="truncate text-sm font-bold text-pitch-950">{{ $team->short_name ?? $team->name }}</span>
                </label>
            @endforeach
        </div>
    </form>
    @else
        <p class="rounded-lg border border-dashed border-pitch-100 bg-white px-6 py-10 text-center text-sm text-pitch-700">
            該当するチームが見つかりませんでした。
        </p>
    @endif
    <div class="mt-6 flex items-center justify-between">
        <a href="{{ route('matches.index') }}" class="text-sm font-semibold text-pitch-700 transition hover:text-gold-500">スキップ</a>
        @if ($teams->isNotEmpty())
            <button type="submit" form="favorite-teams-form"
                    class="rounded-full bg-pitch-950 px-6 py-2.5 text-sm font-bold text-gold-400 transition hover:bg-pitch-800">
                登録
            </button>
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
            return checkbox.closest('.team-card').querySelector('span').textContent.trim();
        });

    const container = document.getElementById('selected-team-names');
    container.innerHTML = '';

    if (names.length === 0) {
        return;
    }

    const label = document.createElement('span');
    label.className = 'text-sm font-semibold text-pitch-700';
    label.textContent = '選択中:';
    container.appendChild(label);

    names.forEach(function (name) {
        const pill = document.createElement('span');
        pill.className = 'rounded-full bg-pitch-950 px-3 py-1 text-xs font-bold text-gold-400';
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
