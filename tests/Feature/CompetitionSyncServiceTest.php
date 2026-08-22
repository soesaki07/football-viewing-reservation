<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Services\CompetitionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompetitionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.football_data.base_url', 'https://api.football-data.test/v4');
        config()->set('services.football_data.api_key', 'test-api-key');
    }

    public function test_it_creates_competitions_from_the_api_response(): void
    {
        Http::fake([
            'https://api.football-data.test/v4/competitions' => Http::response([
                'competitions' => [
                    [
                        'id' => 2021,
                        'area' => ['name' => 'England'],
                        'name' => 'Premier League',
                        'code' => 'PL',
                        'emblem' => 'https://example.test/pl.png',
                    ],
                    [
                        'id' => 2001,
                        'area' => ['name' => 'Europe'],
                        'name' => 'UEFA Champions League',
                        'code' => 'CL',
                        'emblem' => null,
                    ],
                ],
            ]),
        ]);

        $syncedCount = app(CompetitionSyncService::class)->sync();

        $this->assertSame(2, $syncedCount);
        $this->assertDatabaseHas('competitions', [
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Premier League',
            'country_name' => 'England',
            'emblem_url' => 'https://example.test/pl.png',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('competitions', [
            'external_competition_id' => 2001,
            'country_name' => 'Europe',
            'emblem_url' => null,
        ]);
        Http::assertSentCount(1);
    }

    public function test_it_updates_existing_competitions_without_creating_duplicates(): void
    {
        Competition::query()->create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Old name',
            'country_name' => 'Old area',
            'emblem_url' => null,
            'is_active' => false,
        ]);

        Http::fake([
            'https://api.football-data.test/v4/competitions' => Http::response([
                'competitions' => [[
                    'id' => 2021,
                    'area' => ['name' => 'England'],
                    'name' => 'Premier League',
                    'code' => 'PL',
                    'emblem' => 'https://example.test/new-pl.png',
                ]],
            ]),
        ]);

        $service = app(CompetitionSyncService::class);

        $service->sync();
        $service->sync();

        $this->assertDatabaseCount('competitions', 1);
        $this->assertDatabaseHas('competitions', [
            'external_competition_id' => 2021,
            'name' => 'Premier League',
            'country_name' => 'England',
            'emblem_url' => 'https://example.test/new-pl.png',
            'is_active' => false,
        ]);
    }

    public function test_it_rejects_an_invalid_response_without_writing_partial_data(): void
    {
        Http::fake([
            'https://api.football-data.test/v4/competitions' => Http::response([
                'competitions' => [
                    [
                        'id' => 2021,
                        'area' => ['name' => 'England'],
                        'name' => 'Premier League',
                        'code' => 'PL',
                        'emblem' => null,
                    ],
                    [
                        'id' => 9999,
                        'area' => ['name' => 'Unknown'],
                        'name' => 'Missing Code League',
                        'emblem' => null,
                    ],
                ],
            ]),
        ]);

        try {
            app(CompetitionSyncService::class)->sync();
            $this->fail('A validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('competitions.1.code', $exception->errors());
        }

        $this->assertDatabaseCount('competitions', 0);
    }
}
