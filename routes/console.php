<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Football-Data.org API の定期同期（日時は JST 基準で指定する）
// 試合同期は約80秒かかる（大会ごとに sleep(6)）。API の 10回/分 の制限を超えないよう、
// 試合同期（毎時0分）とマスタ同期（3:30 / 3:40）は実行時間が重ならないようにしている。
// 頻度は同期ごとに明示する（共通設定だけをここで付け、頻度の指定漏れで毎時実行にならないようにする）。
$scheduleSync = fn (string $command, string $label) => Schedule::command($command)
    ->timezone('Asia/Tokyo')
    // 前回分が実行中なら起動しない。最悪でも約3.5分で終わるため、ロックは15分で自動解除する
    // （実行中にコンテナが止まってロックが残っても、次の毎時の試合同期がスキップされ続けない）
    ->withoutOverlapping(15)
    ->onFailure(function () use ($command, $label) {
        Log::error("{$label}の定期同期に失敗しました。", ['command' => $command]);
    });

$scheduleSync('app:sync-competitions', '大会')->dailyAt('03:30');
$scheduleSync('app:sync-teams', 'チーム')->dailyAt('03:40');
$scheduleSync('app:sync-football-matches', '試合')->hourly();

unset($scheduleSync);
