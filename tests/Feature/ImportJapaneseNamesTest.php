<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportJapaneseNamesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function makeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'names_ja_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function competitionCsv(string $rows = ''): string
    {
        return $this->makeCsv("external_competition_id,name_ja\n".$rows);
    }

    private function teamCsv(string $rows = ''): string
    {
        return $this->makeCsv("external_team_id,name_ja,short_name_ja\n".$rows);
    }

    private function createCompetition(int $externalId, string $name = 'Premier League'): Competition
    {
        return Competition::create([
            'external_competition_id' => $externalId,
            'code' => 'C'.$externalId,
            'name' => $name,
            'area_name' => 'England',
            'is_active' => true,
        ]);
    }

    public function test_imports_names_from_csv(): void
    {
        $competition = $this->createCompetition(2021);
        $team = Team::create(['external_team_id' => 57, 'name' => 'Arsenal FC', 'short_name' => 'Arsenal']);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv("2021,プレミアリーグ\n"),
            '--teams-file' => $this->teamCsv("57,アーセナル,アーセナル\n"),
        ])->assertSuccessful();

        $this->assertSame('プレミアリーグ', $competition->fresh()->name_ja);
        $this->assertSame('アーセナル', $team->fresh()->name_ja);
        $this->assertSame('アーセナル', $team->fresh()->short_name_ja);
        // 英語名は変わらない
        $this->assertSame('Premier League', $competition->fresh()->name);
        $this->assertSame('Arsenal FC', $team->fresh()->name);
    }

    public function test_running_twice_gives_same_result(): void
    {
        $competition = $this->createCompetition(2021);
        $team = Team::create(['external_team_id' => 57, 'name' => 'Arsenal FC']);
        $options = [
            '--competitions-file' => $this->competitionCsv("2021,プレミアリーグ\n"),
            '--teams-file' => $this->teamCsv("57,アーセナル,\n"),
        ];

        $this->artisan('app:import-japanese-names', $options)->assertSuccessful();
        $this->artisan('app:import-japanese-names', $options)->assertSuccessful();

        $this->assertSame('プレミアリーグ', $competition->fresh()->name_ja);
        $this->assertSame('アーセナル', $team->fresh()->name_ja);
        $this->assertSame(1, Competition::count());
        $this->assertSame(1, Team::count());
    }

    public function test_skips_ids_not_in_database_and_shows_count(): void
    {
        $this->createCompetition(2021);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv("2021,プレミアリーグ\n9999,存在しない大会\n8888,存在しない大会2\n"),
            '--teams-file' => $this->teamCsv("777,存在しないチーム,\n"),
        ])
            ->expectsOutputToContain('大会：反映 1 件')
            ->expectsOutputToContain('大会：DBにないIDのためスキップ 2 件')
            ->expectsOutputToContain('チーム：DBにないIDのためスキップ 1 件')
            ->assertSuccessful();

        $this->assertSame(1, Competition::count());
        $this->assertSame(0, Team::count());
    }

    public function test_blank_values_become_null(): void
    {
        $team = Team::create([
            'external_team_id' => 57,
            'name' => 'Arsenal FC',
            'name_ja' => 'アーセナル',
            'short_name_ja' => 'アーセナル',
        ]);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv(),
            '--teams-file' => $this->teamCsv("57,,\n"),
        ])->assertSuccessful();

        $this->assertNull($team->fresh()->name_ja);
        $this->assertNull($team->fresh()->short_name_ja);
    }

    public function test_ignores_blank_lines_and_bom(): void
    {
        $competition = $this->createCompetition(2021);
        $path = $this->makeCsv("\xEF\xBB\xBFexternal_competition_id,name_ja\n\n2021,プレミアリーグ\n\n");

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $path,
            '--teams-file' => $this->teamCsv(),
        ])->assertSuccessful();

        $this->assertSame('プレミアリーグ', $competition->fresh()->name_ja);
    }

    public function test_fails_when_file_is_missing(): void
    {
        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => '/nonexistent/path/competitions.csv',
            '--teams-file' => $this->teamCsv(),
        ])
            ->expectsOutputToContain('ファイルが見つからないか、読み込めません')
            ->assertFailed();
    }

    public function test_fails_when_header_is_wrong(): void
    {
        $competition = $this->createCompetition(2021);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->makeCsv("id,name\n2021,プレミアリーグ\n"),
            '--teams-file' => $this->teamCsv(),
        ])
            ->expectsOutputToContain('ヘッダーが違います')
            ->assertFailed();

        $this->assertNull($competition->fresh()->name_ja);
    }

    public function test_fails_without_updating_when_a_row_is_malformed(): void
    {
        $competition = $this->createCompetition(2021);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv("2021,プレミアリーグ\nabc,不正なID\n"),
            '--teams-file' => $this->teamCsv(),
        ])
            ->expectsOutputToContain('IDが数字ではありません')
            ->assertFailed();

        // 1行目だけ反映された状態にならない
        $this->assertNull($competition->fresh()->name_ja);
    }

    public function test_fails_when_column_count_is_wrong(): void
    {
        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv("2021,プレミアリーグ,余分\n"),
            '--teams-file' => $this->teamCsv(),
        ])
            ->expectsOutputToContain('列の数が違います')
            ->assertFailed();
    }

    public function test_default_files_contain_all_thirteen_competitions(): void
    {
        $ids = [2013, 2016, 2021, 2001, 2018, 2015, 2002, 2019, 2003, 2017, 2152, 2014, 2000];
        foreach ($ids as $id) {
            $this->createCompetition($id);
        }

        $this->artisan('app:import-japanese-names')
            ->expectsOutputToContain('大会：反映 13 件')
            ->assertSuccessful();

        $this->assertSame(0, Competition::whereNull('name_ja')->count());
        $this->assertSame('プレミアリーグ', Competition::where('external_competition_id', 2021)->value('name_ja'));
    }

    public function test_fails_without_updating_when_team_name_is_too_long(): void
    {
        $team = Team::create(['external_team_id' => 57, 'name' => 'Arsenal FC']);
        $other = Team::create(['external_team_id' => 58, 'name' => 'Other FC']);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv(),
            '--teams-file' => $this->teamCsv("57,アーセナル,\n58,".str_repeat('あ', 256).",\n"),
        ])
            ->expectsOutputToContain('3 行目：name_ja が長すぎます（最大 255 文字）')
            ->assertFailed();

        $this->assertNull($team->fresh()->name_ja);
        $this->assertNull($other->fresh()->name_ja);
    }

    public function test_fails_when_short_name_is_too_long(): void
    {
        $team = Team::create(['external_team_id' => 57, 'name' => 'Arsenal FC']);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv(),
            '--teams-file' => $this->teamCsv('57,アーセナル,'.str_repeat('あ', 101)."\n"),
        ])
            ->expectsOutputToContain('2 行目：short_name_ja が長すぎます（最大 100 文字）')
            ->assertFailed();

        $this->assertNull($team->fresh()->name_ja);
    }

    public function test_fails_when_competition_name_is_too_long(): void
    {
        $competition = $this->createCompetition(2021);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv('2021,'.str_repeat('あ', 256)."\n"),
            '--teams-file' => $this->teamCsv(),
        ])
            ->expectsOutputToContain('2 行目：name_ja が長すぎます（最大 255 文字）')
            ->assertFailed();

        $this->assertNull($competition->fresh()->name_ja);
    }

    public function test_accepts_values_of_exact_maximum_length(): void
    {
        $competition = $this->createCompetition(2021);
        $team = Team::create(['external_team_id' => 57, 'name' => 'Arsenal FC']);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv('2021,'.str_repeat('あ', 255)."\n"),
            '--teams-file' => $this->teamCsv('57,'.str_repeat('い', 255).','.str_repeat('う', 100)."\n"),
        ])->assertSuccessful();

        $this->assertSame(255, mb_strlen($competition->fresh()->name_ja));
        $this->assertSame(255, mb_strlen($team->fresh()->name_ja));
        $this->assertSame(100, mb_strlen($team->fresh()->short_name_ja));
    }

    public function test_fails_without_updating_when_id_is_duplicated(): void
    {
        $team = Team::create(['external_team_id' => 57, 'name' => 'Arsenal FC']);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv(),
            '--teams-file' => $this->teamCsv("57,アーセナル,\n57,別名,\n"),
        ])
            ->expectsOutputToContain('3 行目：ID 57 が重複しています')
            ->assertFailed();

        $this->assertNull($team->fresh()->name_ja);
    }

    public function test_shows_applied_count_for_teams(): void
    {
        Team::create(['external_team_id' => 57, 'name' => 'Arsenal FC']);
        Team::create(['external_team_id' => 58, 'name' => 'Other FC']);

        $this->artisan('app:import-japanese-names', [
            '--competitions-file' => $this->competitionCsv(),
            '--teams-file' => $this->teamCsv("57,アーセナル,\n58,別名,\n"),
        ])
            ->expectsOutputToContain('チーム：反映 2 件')
            ->assertSuccessful();
    }
}
