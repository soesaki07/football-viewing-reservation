<?php

namespace App\Services;

use App\Models\Competition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompetitionSyncService
{
    public function __construct(
        private readonly FootballDataService $footballDataService,
    ) {}

    /**
     * Synchronize competitions from Football-Data.org.
     *
     * @return int The number of competitions contained in the API response.
     */
    public function sync(): int
    {
        $payload = $this->footballDataService->getCompetitions();

        $validated = Validator::make($payload, [
            'competitions' => ['required', 'array'],
            'competitions.*.id' => ['required', 'integer', 'distinct'],
            'competitions.*.code' => ['required', 'string', 'max:20', 'distinct'],
            'competitions.*.name' => ['required', 'string', 'max:255'],
            'competitions.*.area' => ['sometimes', 'array'],
            'competitions.*.area.name' => ['nullable', 'string', 'max:100'],
            'competitions.*.emblem' => ['nullable', 'string', 'max:2048'],
        ])->validate();

        return DB::transaction(function () use ($validated): int {
            foreach ($validated['competitions'] as $competition) {
                Competition::query()->updateOrCreate(
                    ['external_competition_id' => $competition['id']],
                    [
                        'code' => $competition['code'],
                        'name' => $competition['name'],
                        'country_name' => $competition['area']['name'] ?? null,
                        'emblem_url' => $competition['emblem'] ?? null,
                    ],
                );
            }

            return count($validated['competitions']);
        });
    }
}
