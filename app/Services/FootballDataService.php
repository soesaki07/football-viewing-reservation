<?php

namespace App\Services;

use App\Models\Competition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FootballDataService
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.football_data.base_url')
            ?? throw new \RuntimeException('Football Data base URL is not configured.');
        $this->apiKey = config('services.football_data.api_key')
            ?? throw new \RuntimeException('Football Data API key is not configured.');
    }

    private function client()
    {
        return Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
        ])->timeout(10);
    }

    public function getCompetitions(): array
    {
        $response = $this->client()
            ->get($this->baseUrl.'/competitions');
        $response->throw();

        return $response->json();
    }

    public function getTeams(): array
    {
        $teams = [];
        $competitionCodes = Competition::pluck('code');

        try {
            foreach ($competitionCodes as $competitionCode) {
                $response = $this->client()
                    ->get($this->baseUrl.'/competitions/'.$competitionCode.'/teams')
                    ->throw()->json();
                sleep(6);
                if (! isset($response['teams']) || ! is_array($response['teams'])) {
                    throw new \RuntimeException('Football-Data.org APIのレスポンスにteamsキー（配列）が含まれていません。');
                } else {
                    foreach ($response['teams'] as $team) {
                        $teams[] = $team;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('大会情報の同期に失敗しました', ['exception' => $e]);
        }

        return ['teams' => $teams];
    }
}
