<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\FootballDataService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-teams')]
#[Description('Football-Data.org APIからチーム情報を取得しDBへ同期する')]
class SyncTeams extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service): int
    {
        try {
            $response = $service->getTeams();

            if (! isset($response['teams']) || ! is_array($response['teams'])) {
                throw new \RuntimeException('Football-Data.org APIのレスポンスにteamsキー（配列）が含まれていません。');
            } else {
                $teams = [];

                foreach ($response['teams'] as $team) {
                    $teams[] = [
                        'external_team_id' => $team['id'],
                        'name' => $team['name'],
                        'short_name' => $team['shortName'],
                        'tla' => $team['tla'],
                        'crest_url' => $team['crest'],
                        'country_name' => $team['area']['name'],

                    ];
                }
            }

            Team::upsert(
                $teams,
                ['external_team_id'],
                ['name', 'short_name', 'tla', 'crest_url', 'country_name'],
            );

            $this->info('同期に成功しました。');

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('チーム情報の同期に失敗しました。', ['exception' => $e]);

            return self::FAILURE;
        }
    }
}
