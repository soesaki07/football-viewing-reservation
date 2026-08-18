<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
}
