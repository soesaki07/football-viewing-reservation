<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FootballDataService
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.football_data.base_url');
        $this->apiKey = config('services.football_data.api_key');
    }

    private function client()
    {
        return Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
        ]);
    }

    public function getCompetitions(): array
    {
        return $this->client()
            ->get($this->baseUrl.'/competitions')
            ->json();
    }
}
