<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 指定した Artisan コマンドのスケジュールイベントを取得する。
     */
    private function findEvent(string $command): Event
    {
        // 空の Schedule に差し替え、routes/console.php を読み込んで定義を確認する（重複登録を避けるため）
        $this->app->instance(Schedule::class, new Schedule('UTC'));
        Facade::clearResolvedInstance(Schedule::class);
        require dirname(__DIR__, 2).'/routes/console.php';

        /** @var Collection<int, Event> $events */
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains($event->command, $command));

        $this->assertCount(1, $events, "{$command} がスケジュールに1件だけ登録されていること");

        return $events->first();
    }

    public function test_試合同期は毎時0分に_js_tで登録されている(): void
    {
        $event = $this->findEvent('app:sync-football-matches');

        $this->assertSame('0 * * * *', $event->expression);
        $this->assertSame('Asia/Tokyo', (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_大会同期は毎日3時30分に_js_tで登録されている(): void
    {
        $event = $this->findEvent('app:sync-competitions');

        $this->assertSame('30 3 * * *', $event->expression);
        $this->assertSame('Asia/Tokyo', (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_チーム同期は毎日3時40分に_js_tで登録されている(): void
    {
        $event = $this->findEvent('app:sync-teams');

        $this->assertSame('40 3 * * *', $event->expression);
        $this->assertSame('Asia/Tokyo', (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_失敗時はエラーログに記録される(): void
    {
        foreach (['app:sync-competitions', 'app:sync-teams', 'app:sync-football-matches'] as $command) {
            $event = $this->findEvent($command);

            Log::shouldReceive('error')
                ->once()
                ->with(Mockery::type('string'), ['command' => $command]);

            // コマンドが失敗（終了コード 1）した状態で、実行後のコールバックを呼ぶ
            $event->exitCode = 1;
            $event->callAfterCallbacks($this->app);
        }
    }

    public function test_成功時はエラーログに記録されない(): void
    {
        $event = $this->findEvent('app:sync-teams');

        Log::shouldReceive('error')->never();

        $event->exitCode = 0;
        $event->callAfterCallbacks($this->app);
    }

    public function test_多重起動防止のロックを_db_キャッシュで取得できる(): void
    {
        // 本番・開発と同じ database キャッシュ（cache / cache_locks テーブル）でロックを確認する
        config(['cache.default' => 'database']);

        $event = $this->findEvent('app:sync-football-matches');

        $this->assertTrue($event->mutex->create($event), '1回目はロックを取得できる');
        $this->assertFalse($event->mutex->create($event), '実行中の2回目はロックを取得できない');

        $event->mutex->forget($event);

        $this->assertTrue($event->mutex->create($event), '解放後は再びロックを取得できる');
    }
}
