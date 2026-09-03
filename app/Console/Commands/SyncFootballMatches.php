<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Services\FootballDataService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-football-matches')]
#[Description('Football-Data.org APIから試合情報を取得しDBへ同期する')]
class SyncFootballMatches extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service): int
    {
        try {
            $response = $service->getMatches();
            $competitionMap = Competition::pluck('id', 'external_competition_id');
            $teamMap = Team::pluck('id', 'external_team_id');

            if (! isset($response['footballMatches']) || ! is_array($response['footballMatches'])) {
                throw new \RuntimeException('Football-Data.org APIのレスポンスにfootballMatchesキー（配列）が含まれていません。');
            }

            $footballMatches = [];

            foreach ($response['footballMatches'] as $footballMatch) {
                $competitionExternalId = $footballMatch['competition']['id'];
                $homeTeamExternalId = $footballMatch['homeTeam']['id'];
                $awayTeamExternalId = $footballMatch['awayTeam']['id'];

                if (! isset($competitionMap[$competitionExternalId]) || ! isset($teamMap[$homeTeamExternalId]) || ! isset($teamMap[$awayTeamExternalId])) {
                    continue;
                }
                $footballMatches[] = [
                    'external_match_id' => $footballMatch['id'],
                    'competition_id' => $competitionMap[$footballMatch['competition']['id']],
                    'home_team_id' => $teamMap[$footballMatch['homeTeam']['id']],
                    'away_team_id' => $teamMap[$footballMatch['awayTeam']['id']],
                    'season_start_year' => Carbon::parse($footballMatch['season']['startDate'])->format('Y'),
                    'match_day' => $footballMatch['matchday'],
                    'stage' => $footballMatch['stage'],
                    'kickoff_at' => Carbon::parse($footballMatch['utcDate'])->format('Y-m-d H:i:s'),
                    'status' => $footballMatch['status'],
                    'home_score' => $footballMatch['score']['fullTime']['home'],
                    'away_score' => $footballMatch['score']['fullTime']['away'],
                    'venue' => $footballMatch['venue'] ?? null,
                    'last_api_synced_at' => Carbon::now(),
                ];
            }

            FootballMatch::upsert(
                $footballMatches,
                ['external_match_id'],
                [
                    'competition_id',
                    'home_team_id',
                    'away_team_id',
                    'season_start_year',
                    'match_day',
                    'stage',
                    'kickoff_at',
                    'status',
                    'home_score',
                    'away_score',
                    'venue',
                    'last_api_synced_at',
                ],
            );

            $this->info('同期に成功しました。');

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('試合情報の同期に失敗しました。', ['exception' => $e]);

            return self::FAILURE;
        }
    }
}
