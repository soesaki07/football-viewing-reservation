<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 小さな手直し（予約確認のエラーの順番、検索の入力の保持、ページ番号の丸め、ページ送りの日本語化、チーム名の並び）。
 */
class SmallUiImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private Competition $pl;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pl = Competition::create(['external_competition_id' => 2021, 'code' => 'PL', 'name' => 'Premier League', 'area_name' => 'England', 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTeam(string $name, array $attributes = []): Team
    {
        return Team::create(array_merge([
            'external_team_id' => 5000 + (++$this->seq),
            'name' => $name,
        ], $attributes));
    }

    private function makeMatch(Team $home, Team $away, string $kickoffUtc): FootballMatch
    {
        return FootballMatch::create([
            'external_match_id' => 700000 + (++$this->seq),
            'competition_id' => $this->pl->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'season_start_year' => 2026,
            'match_day' => 1,
            'stage' => 'REGULAR_SEASON',
            'kickoff_at' => $kickoffUtc,
            'status' => 'SCHEDULED',
            'last_api_synced_at' => now(),
        ]);
    }

    /**
     * 画面に出た試合カードのリンク先の ID を並び順どおりに返す。
     *
     * @return array<int, int>
     */
    private function listedIds(string $html): array
    {
        preg_match_all('#/matches/(\d+)"#', $html, $found);

        return array_map('intval', $found[1]);
    }

    // ---- A3：予約済みのチェックを入力チェックより先に行う ----

    /**
     * 満席の座席を持つ放映と、その試合を別の座席で予約済みのユーザーを用意する。
     *
     * @return array{0: User, 1: Broadcast, 2: BroadcastSeatType, 3: FootballMatch}
     */
    private function reservedUserAndFullSeat(): array
    {
        $this->travelTo('2026-10-31 12:00:00');
        $match = $this->makeMatch($this->makeTeam('Home FC'), $this->makeTeam('Away FC'), '2026-11-05 12:00:00');
        $shop = Shop::factory()->create(['user_id' => User::factory()->create()->id, 'status' => Shop::STATUS_PUBLISHED]);
        $broadcast = Broadcast::factory()->forMatch($match)->create([
            'shop_id' => $shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
            'reservation_start_at' => now()->subDay(),
            'reservation_end_at' => now()->addDay(),
        ]);
        $makeSeat = function (int $capacity) use ($broadcast, $shop) {
            $seatType = SeatType::factory()->create(['shop_id' => $shop->id, 'name' => '席'.$capacity, 'default_capacity' => $capacity, 'default_price' => 3000, 'is_active' => true]);

            return BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);
        };
        $reservedSeat = $makeSeat(10);
        $fullSeat = $makeSeat(1);

        $user = User::factory()->create();
        Reservation::factory()->state(['broadcast_seat_type_id' => $reservedSeat->id, 'number_of_people' => 1, 'user_id' => $user->id])->create();
        // 別の人が満席の座席を埋める
        Reservation::factory()->state(['broadcast_seat_type_id' => $fullSeat->id, 'number_of_people' => 1, 'user_id' => User::factory()->create()->id])->create();

        return [$user, $broadcast, $fullSeat, $match];
    }

    public function test_reserved_user_sees_already_reserved_message_instead_of_input_errors_at_confirm(): void
    {
        [$user, $broadcast, $fullSeat, $match] = $this->reservedUserAndFullSeat();

        // 満席の座席（残席不足）・存在しない座席・人数不正のどれでも、予約済みのメッセージが優先される
        foreach ([
            ['broadcast_seat_type_id' => $fullSeat->id, 'number_of_people' => 1],
            ['broadcast_seat_type_id' => 999999, 'number_of_people' => 1],
            ['broadcast_seat_type_id' => $fullSeat->id, 'number_of_people' => 0],
        ] as $input) {
            $this->actingAs($user)->post(route('reservations.confirm', $broadcast), $input)
                ->assertRedirect(route('matches.show', $match->id))
                ->assertSessionHas('error', 'この試合はすでに予約済みです。')
                ->assertSessionDoesntHaveErrors();
        }
    }

    public function test_unreserved_user_still_gets_input_errors_at_confirm(): void
    {
        [, $broadcast, $fullSeat] = $this->reservedUserAndFullSeat();

        $this->actingAs(User::factory()->create())
            ->from(route('reservations.create', $broadcast))
            ->post(route('reservations.confirm', $broadcast), ['broadcast_seat_type_id' => $fullSeat->id, 'number_of_people' => 1])
            ->assertRedirect(route('reservations.create', $broadcast))
            ->assertSessionHasErrors('number_of_people')
            ->assertSessionMissing('error');
    }

    public function test_reserved_user_with_full_seat_in_session_gets_already_reserved_message_at_store(): void
    {
        [$user, $broadcast, $fullSeat, $match] = $this->reservedUserAndFullSeat();

        // 満席の座席を入力したセッションのまま確定に進んでも、予約済みのメッセージが出る（入力エラーの文言ではない）
        $this->actingAs($user)
            ->withSession(['reservation_input.'.$broadcast->id => ['broadcast_seat_type_id' => $fullSeat->id, 'number_of_people' => 1, 'unit_price' => 3000]])
            ->post(route('reservations.store', $broadcast))
            ->assertRedirect(route('reservations.create', $broadcast))
            ->assertSessionHas('error', 'この試合はすでに予約済みです。');

        $this->actingAs($user)->followingRedirects()->get(route('reservations.create', $broadcast))
            ->assertSee('この試合はすでに予約済みです。');
        $this->assertSame(2, Reservation::count());
    }

    public function test_reserved_user_gets_closed_message_at_store_for_unreservable_broadcast(): void
    {
        [$user, $broadcast, $fullSeat] = $this->reservedUserAndFullSeat();
        // 受付を終了させる（予約できない放映）
        $broadcast->update(['reservation_end_at' => now()->subHour()]);

        $this->actingAs($user)
            ->withSession(['reservation_input.'.$broadcast->id => ['broadcast_seat_type_id' => $fullSeat->id, 'number_of_people' => 1, 'unit_price' => 3000]])
            ->post(route('reservations.store', $broadcast))
            ->assertRedirect(route('reservations.create', $broadcast))
            ->assertSessionHas('error', '予約の受付は終了しました。');
    }

    // ---- B3：入力エラーのとき、入力した値を残す ----

    public function test_search_form_keeps_input_when_validation_fails(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('matches.index', [
            'date' => '2026-13-45',
            'team' => 'Arsenal',
            'competition' => 'XX',
        ]));

        $response->assertOk()
            ->assertSee('日付は YYYY-MM-DD の形式で正しい日付を指定してください。')
            ->assertSee('name="date" value="2026-13-45"', false)
            ->assertSee('name="team" value="Arsenal"', false);
        $this->assertStringNotContainsString('検索結果（', $response->getContent());
    }

    public function test_search_form_keeps_selected_competition_when_validation_fails(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('matches.index', ['date' => 'abc', 'competition' => 'PL']));

        $response->assertOk();
        // 下にある大会の切り替えにも PL が選ばれているので、検索フォームの部分だけを調べる
        $this->assertSame(1, preg_match('#role="search".*?</form>#s', $response->getContent(), $form));
        $this->assertMatchesRegularExpression('#<option value="PL"\s+selected#', $form[0]);
    }

    public function test_search_form_escapes_kept_input(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('matches.index', ['date' => 'abc', 'team' => '<script>alert(1)</script>']));

        $response->assertOk()
            ->assertSee('name="team" value="&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_search_form_drops_invalid_utf8_and_array_input(): void
    {
        $user = User::factory()->create();

        // UTF-8 として正しくない値：残さず、500 にもならない
        $this->actingAs($user)->get('/matches?date=abc&team=%FF%FE&competition=%FF')
            ->assertOk()
            ->assertSee('name="team" value=""', false)
            ->assertSee('name="date" value="abc"', false);

        // 配列：残さず、500 にもならない
        $this->actingAs($user)->get('/matches?date[]=a&team[]=b&competition[]=c')
            ->assertOk()
            ->assertSee('name="team" value=""', false)
            ->assertSee('name="date" value=""', false);
    }

    // ---- B6：最後のページより大きいページ番号 ----

    /**
     * 今後の試合を $count 件作り、キックオフの早い順の ID を返す。
     *
     * @return array<int, int>
     */
    private function makeUpcomingMatches(int $count): array
    {
        $this->travelTo('2026-10-31 12:00:00');
        $home = $this->makeTeam('Alpha FC');
        $away = $this->makeTeam('Beta FC');
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $ids[] = $this->makeMatch($home, $away, now()->addMinutes($i)->toDateTimeString())->id;
        }

        return $ids;
    }

    public function test_page_beyond_last_page_shows_last_page(): void
    {
        $ids = $this->makeUpcomingMatches(61);
        $user = User::factory()->create();
        $lastPageIds = array_slice($ids, 60);

        // 最後のページ（4）、最後のページ＋1、10,000、桁数の多い値のどれでも、最後のページの試合が出る
        foreach (['4', '5', '10000', '99999999999999999999'] as $page) {
            $response = $this->actingAs($user)->get(route('matches.index', ['team' => 'Alpha', 'page' => $page]));
            $response->assertOk()->assertSee('検索結果（61件）', false);
            $this->assertSame($lastPageIds, $this->listedIds($response->getContent()), "page={$page}");
            $this->assertStringNotContainsString('条件に合う試合はありません。', $response->getContent());
        }
    }

    public function test_query_count_for_football_matches_is_same_beyond_last_page(): void
    {
        $this->makeUpcomingMatches(61);
        $user = User::factory()->create();
        // 認証ユーザーなどの初回の読み込みを済ませるため、先に1回アクセスする
        $this->actingAs($user)->get(route('matches.index', ['team' => 'Alpha']))->assertOk();

        $countQueries = function (int $page) use ($user): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($user)->get(route('matches.index', ['team' => 'Alpha', 'page' => $page]))->assertOk();
            $queries = array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], 'football_matches'));

            return count($queries);
        };

        $normal = $countQueries(2);
        $this->assertGreaterThan(0, $normal);
        $this->assertSame($normal, $countQueries(4));
        $this->assertSame($normal, $countQueries(5));
        $this->assertSame($normal, $countQueries(10000));
    }

    public function test_page_within_range_is_unchanged_and_zero_results_message_stays(): void
    {
        $ids = $this->makeUpcomingMatches(61);
        $user = User::factory()->create();

        $this->assertSame(
            array_slice($ids, 20, 20),
            $this->listedIds($this->actingAs($user)->get(route('matches.index', ['team' => 'Alpha', 'page' => 2]))->getContent())
        );

        $this->actingAs($user)->get(route('matches.index', ['team' => 'NoSuchTeam', 'page' => 10000]))
            ->assertOk()
            ->assertSee('検索結果（0件）', false)
            ->assertSee('条件に合う試合はありません。');
    }

    // ---- C1：ページ送りの日本語化 ----

    public function test_search_pagination_is_japanese(): void
    {
        $this->makeUpcomingMatches(35);

        $response = $this->actingAs(User::factory()->create())->get(route('matches.index', ['team' => 'Alpha']));

        $response->assertOk()
            ->assertSee('件中', false)
            ->assertSee('件を表示', false)
            ->assertSee('次へ', false)
            ->assertSee('ページ送り', false)
            ->assertSee('2ページ目へ移動', false)
            ->assertDontSee('Showing')
            ->assertDontSee('results')
            ->assertDontSee('Next')
            ->assertDontSee('Previous')
            ->assertDontSee('Go to page');
        $this->assertMatchesRegularExpression('#35</span>件中\s*<span class="font-medium">1</span>〜<span class="font-medium">20</span>件を表示#', $response->getContent());
    }

    // ---- D2：お気に入り登録のカードは略称の順 ----

    public function test_select_teams_cards_are_ordered_by_displayed_short_name(): void
    {
        // 正式名の順（あ→い→う→え→お）と略称の順が異なるようにする
        $psg = $this->makeTeam('Paris Saint-Germain FC', ['name_ja' => 'あ・パリ・サンジェルマン', 'short_name_ja' => 'PSG']);
        $apiOnly = $this->makeTeam('Beta FC', ['name_ja' => 'い・ベータ', 'short_name' => 'Beta']);
        $nameOnly = $this->makeTeam('Gamma FC', ['name_ja' => 'う・ガンマ']);
        // 略称が同じチームは、ID の順（先に作った方が先）
        $samePsgLater = $this->makeTeam('Zeta FC', ['name_ja' => 'え・ゼータ', 'short_name_ja' => 'PSG']);
        $english = $this->makeTeam('Delta FC');
        $other = $this->makeTeam('Other FC');
        foreach ([$psg, $apiOnly, $nameOnly, $samePsgLater, $english] as $team) {
            $this->makeMatch($team, $other, '2026-11-05 12:00:00');
        }

        $html = $this->actingAs(User::factory()->create())->get('/select/teams')->getContent();

        // 略称（日本語略称 → API 略称 → 日本語名 → 英語名）の順。Beta, Delta FC, Other FC, PSG(ID 小), PSG(ID 大), う・ガンマ
        preg_match_all('#name="team_ids\[\]" value="(\d+)"#', $html, $found);
        $this->assertSame(
            [$apiOnly->id, $english->id, $other->id, $psg->id, $samePsgLater->id, $nameOnly->id],
            array_map('intval', $found[1])
        );
    }

    // ---- D3：試合一覧のお気に入りチームは日本語名の順 ----

    public function test_matches_index_favorite_teams_are_ordered_by_japanese_name(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        $user = User::factory()->create();
        $other = $this->makeTeam('Other FC');
        // 日本語名の順は か → さ → た。略称の順（Z → A → M）とは違うので、日本語名で並べていることが分かる
        // ID の順も日本語名の順と違えておく（third → first → second の順に作る）
        $third = $this->makeTeam('Third FC', ['name_ja' => 'たけ', 'short_name_ja' => 'Mタケ']);
        $first = $this->makeTeam('First FC', ['name_ja' => 'かもめ', 'short_name_ja' => 'Zカモメ']);
        $second = $this->makeTeam('Second FC', ['name_ja' => 'さくら', 'short_name_ja' => 'Aサクラ']);
        // 登録順も日本語名の順と違えておく
        $user->favoriteTeams()->attach([$second->id, $third->id, $first->id]);
        foreach ([$first, $second, $third] as $team) {
            $this->makeMatch($team, $other, '2026-11-05 12:00:00');
        }

        $response = $this->actingAs($user)->get(route('matches.index'));

        // 上のラベル（3つ）と、チームごとのブロックの見出し（3つ）の両方が、日本語名の順
        $response->assertOk()->assertSeeInOrder(['Zカモメ', 'Aサクラ', 'Mタケ', 'Zカモメ', 'Aサクラ', 'Mタケ']);
        preg_match_all('#<h3 class="mb-4[^>]*>\s*(?:<img[^>]*>\s*)?(.*?)\s*</h3>#s', $response->getContent(), $found);
        $this->assertSame(['Zカモメ', 'Aサクラ', 'Mタケ'], $found[1]);
    }
}
