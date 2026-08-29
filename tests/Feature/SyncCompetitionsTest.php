<?php

namespace Tests\Feature;

use App\Models\Competition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCompetitionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.football_data.base_url' => 'https://api.football-data.org/v4',
            'services.football_data.api_key' => 'test-api-key',
        ]);
    }

    private function fakeCompetitionsResponse(): void
    {
        Http::fake([
            '*/competitions' => Http::response([
                'competitions' => [
                    [
                        'id' => 2000,
                        'code' => 'WC',
                        'type' => 'CUP',
                        'name' => 'FIFA World Cup',
                        'area' => ['name' => 'World'],
                        'emblem' => 'https://crests.football-data.org/wc.png',
                    ],
                ],
            ]),
        ]);
    }

    public function test_new_competition_is_saved_to_database(): void
    {
        $this->fakeCompetitionsResponse();

        $this->artisan('app:sync-competitions')->assertSuccessful();

        $this->assertDatabaseHas('competitions', [
            'external_competition_id' => 2000,
            'code' => 'WC',
            'type' => 'CUP',
            'name' => 'FIFA World Cup',
            'area_name' => 'World',
            'emblem_url' => 'https://crests.football-data.org/wc.png',
        ]);
    }

    public function test_existing_competition_is_updated_on_all_columns(): void
    {
        Competition::create([
            'external_competition_id' => 2000,
            'code' => 'OLD',
            'type' => 'OLD_TYPE',
            'name' => 'Old Name',
            'area_name' => 'Old Area',
            'emblem_url' => 'https://old.example.com/old.png',
            'is_active' => false,
        ]);

        $this->fakeCompetitionsResponse();

        $this->artisan('app:sync-competitions')->assertSuccessful();

        $this->assertDatabaseCount('competitions', 1);
        $this->assertDatabaseHas('competitions', [
            'external_competition_id' => 2000,
            'code' => 'WC',
            'type' => 'CUP',
            'name' => 'FIFA World Cup',
            'area_name' => 'World',
            'emblem_url' => 'https://crests.football-data.org/wc.png',
            'is_active' => true,
        ]);
    }

    public function test_sync_fails_and_skips_save_when_api_returns_error_response(): void
    {
        Http::fake([
            '*/competitions' => Http::response([], 500),
        ]);

        $this->artisan('app:sync-competitions')->assertFailed();

        $this->assertDatabaseCount('competitions', 0);
    }

    public function test_sync_fails_and_skips_save_when_connection_fails(): void
    {
        Http::fake([
            '*/competitions' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->artisan('app:sync-competitions')->assertFailed();

        $this->assertDatabaseCount('competitions', 0);
    }
}
