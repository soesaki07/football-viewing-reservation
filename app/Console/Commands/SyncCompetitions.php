<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Services\FootballDataService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-competitions')]
#[Description('Football-Data.org APIから大会情報を取得しDBへ同期する')]
class SyncCompetitions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service): int
    {
        try {
            $response = $service->getCompetitions();

            $competitions = [];

            foreach ($response['competitions'] as $competition) {
                $competitions[] = [
                    'external_competition_id' => $competition['id'],
                    'code' => $competition['code'],
                    'type' => $competition['type'],
                    'name' => $competition['name'],
                    'area_name' => $competition['area']['name'],
                    'emblem_url' => $competition['emblem'],
                    'is_active' => true,
                ];
            }

            Competition::upsert(
                $competitions,
                ['external_competition_id'],
                ['name', 'code', 'type', 'area_name', 'emblem_url', 'is_active'],
            );

            $this->info('同期に成功しました。');

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('大会情報の同期に失敗しました', ['exception' => $e]);

            return self::FAILURE;
        }
    }
}
