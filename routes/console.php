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
$syncCommands = [
    ['command' => 'app:sync-competitions', 'label' => '大会'],
    ['command' => 'app:sync-teams', 'label' => 'チーム'],
    ['command' => 'app:sync-football-matches', 'label' => '試合'],
];

foreach ($syncCommands as $sync) {
    $event = Schedule::command($sync['command'])
        ->timezone('Asia/Tokyo')
        ->withoutOverlapping(120) // 前回分が実行中なら起動しない（ロックは最大120分で自動解除）
        ->onFailure(function () use ($sync) {
            Log::error("{$sync['label']}の定期同期に失敗しました。", ['command' => $sync['command']]);
        });

    match ($sync['command']) {
        'app:sync-competitions' => $event->dailyAt('03:30'),
        'app:sync-teams' => $event->dailyAt('03:40'),
        default => $event->hourly(),
    };
}
