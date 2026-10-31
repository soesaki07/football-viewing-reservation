<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:import-japanese-names {--competitions-file= : 大会の対応表（CSV）のパス} {--teams-file= : チームの対応表（CSV）のパス}')]
#[Description('対応表（CSV）から大会・チームの日本語名をDBへ取り込む')]
class ImportJapaneseNames extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $competitionsFile = $this->option('competitions-file') ?: database_path('data/competition_names_ja.csv');
        $teamsFile = $this->option('teams-file') ?: database_path('data/team_names_ja.csv');

        try {
            // DB を更新する前に、2つのファイルとも読み込めることを確認する
            $competitionRows = $this->readCsv($competitionsFile, ['external_competition_id', 'name_ja'], ['name_ja' => 255]);
            $teamRows = $this->readCsv($teamsFile, ['external_team_id', 'name_ja', 'short_name_ja'], ['name_ja' => 255, 'short_name_ja' => 100]);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // 片方だけ反映された状態を残さないよう、まとめて更新する
        [$competitionResult, $teamResult] = DB::transaction(function () use ($competitionRows, $teamRows) {
            $competitionResult = $this->importRows($competitionRows, Competition::class, 'external_competition_id', ['name_ja']);
            $teamResult = $this->importRows($teamRows, Team::class, 'external_team_id', ['name_ja', 'short_name_ja']);

            return [$competitionResult, $teamResult];
        });

        $this->info("大会：反映 {$competitionResult['updated']} 件");
        $this->info("チーム：反映 {$teamResult['updated']} 件");

        foreach (['大会' => $competitionResult, 'チーム' => $teamResult] as $label => $result) {
            if ($result['skipped'] > 0) {
                $this->warn("{$label}：DBにないIDのためスキップ {$result['skipped']} 件");
            }
        }

        return self::SUCCESS;
    }

    /**
     * CSV を読み込み、ヘッダーをキーにした行の配列を返す。
     *
     * @param  list<string>  $expectedHeader
     * @param  array<string, int>  $maxLengths  列ごとの最大文字数
     * @return list<array<string, string>>
     *
     * @throws \RuntimeException ファイルがない・読めない・ヘッダーや行の形式が違うとき
     */
    private function readCsv(string $path, array $expectedHeader, array $maxLengths): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException("ファイルが見つからないか、読み込めません：{$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("ファイルを開けません：{$path}");
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if (is_array($header) && isset($header[0])) {
                // 先頭の BOM（Excel などで保存したとき）を取り除く
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            }
            if ($header !== $expectedHeader) {
                throw new \RuntimeException("ヘッダーが違います（{$path}）。期待する形式：".implode(',', $expectedHeader));
            }

            $rows = [];
            $seenIds = [];
            $lineNumber = 1;
            while (($fields = fgetcsv($handle, escape: '')) !== false) {
                $lineNumber++;

                // 空行は読み飛ばす
                if ($fields === [null]) {
                    continue;
                }

                if (count($fields) !== count($expectedHeader)) {
                    throw new \RuntimeException("列の数が違います（{$path} の {$lineNumber} 行目）");
                }

                $row = array_combine($expectedHeader, array_map('trim', $fields));
                if (! ctype_digit($row[$expectedHeader[0]])) {
                    throw new \RuntimeException("IDが数字ではありません（{$path} の {$lineNumber} 行目）");
                }

                $id = $row[$expectedHeader[0]];
                if (isset($seenIds[(int) $id])) {
                    throw new \RuntimeException("{$path} の {$lineNumber} 行目：ID {$id} が重複しています");
                }
                $seenIds[(int) $id] = true;

                foreach ($maxLengths as $column => $maxLength) {
                    if (mb_strlen($row[$column]) > $maxLength) {
                        throw new \RuntimeException("{$path} の {$lineNumber} 行目：{$column} が長すぎます（最大 {$maxLength} 文字）");
                    }
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * 行ごとに、外部IDが一致するレコードの日本語名を更新する。空欄は NULL にする。
     *
     * @param  list<array<string, string>>  $rows
     * @param  class-string<Competition|Team>  $modelClass
     * @param  list<string>  $columns
     * @return array{updated: int, skipped: int}
     */
    private function importRows(array $rows, string $modelClass, string $idColumn, array $columns): array
    {
        $updated = 0;
        $skipped = 0;

        // 対象をまとめて取得してから保存する（1行ずつ検索しない）
        $models = $modelClass::whereIn($idColumn, array_map(fn (array $row) => (int) $row[$idColumn], $rows))
            ->get()
            ->keyBy($idColumn);

        foreach ($rows as $row) {
            $model = $models->get((int) $row[$idColumn]);

            if ($model === null) {
                $skipped++;

                continue;
            }

            foreach ($columns as $column) {
                $model->{$column} = $row[$column] === '' ? null : $row[$column];
            }
            $model->save();
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }
}
