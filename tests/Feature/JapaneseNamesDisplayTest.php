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
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class JapaneseNamesDisplayTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-31 12:00:00');
    }

    /**
     * 日本語名つきの試合を作る（ホーム：日本語名と略称あり、アウェイ：日本語名なし）。
     */
    private function createJapaneseMatch(): FootballMatch
    {
        $match = $this->createFootballMatch();
        $match->competition->update(['name_ja' => 'プレミアリーグ']);
        $match->homeTeam->update(['name_ja' => 'アーセナル', 'short_name' => 'Arsenal', 'short_name_ja' => 'アーセナル略']);

        return $match;
    }

    public function test_team_display_name_falls_back_to_english(): void
    {
        $this->assertSame('アーセナル', (new Team(['name' => 'Arsenal FC', 'name_ja' => 'アーセナル']))->displayName());
        $this->assertSame('Arsenal FC', (new Team(['name' => 'Arsenal FC']))->displayName());
    }

    public function test_team_display_short_name_falls_back_in_order(): void
    {
        $this->assertSame('アーセ', (new Team(['name' => 'Arsenal FC', 'name_ja' => 'アーセナル', 'short_name' => 'Arsenal', 'short_name_ja' => 'アーセ']))->displayShortName());
        $this->assertSame('Arsenal', (new Team(['name' => 'Arsenal FC', 'name_ja' => 'アーセナル', 'short_name' => 'Arsenal']))->displayShortName());
        // 略称がどちらもなければ、日本語のチーム名
        $this->assertSame('アーセナル', (new Team(['name' => 'Arsenal FC', 'name_ja' => 'アーセナル']))->displayShortName());
        $this->assertSame('Arsenal FC', (new Team(['name' => 'Arsenal FC']))->displayShortName());
    }

    public function test_competition_display_name_falls_back_to_english(): void
    {
        $this->assertSame('プレミアリーグ', (new Competition(['name' => 'Premier League', 'name_ja' => 'プレミアリーグ']))->displayName());
        $this->assertSame('Premier League', (new Competition(['name' => 'Premier League']))->displayName());
    }

    public function test_match_show_displays_japanese_name_with_english_fallback(): void
    {
        $match = $this->createJapaneseMatch();

        $this->actingAs(User::factory()->create())->get(route('matches.show', $match))
            ->assertOk()
            ->assertSee('アーセナル')
            ->assertDontSee('Arsenal FC')
            ->assertSee('Chelsea FC');
    }

    public function test_match_index_displays_japanese_competition_in_dropdown(): void
    {
        $this->createJapaneseMatch();

        $this->actingAs(User::factory()->create())->get(route('matches.index'))
            ->assertOk()
            ->assertSee('プレミアリーグ')
            ->assertDontSee('Premier League');
    }

    public function test_match_index_displays_japanese_short_name_for_favorite_team(): void
    {
        $match = $this->createJapaneseMatch();
        $user = User::factory()->create();
        $user->favoriteTeams()->sync([$match->home_team_id]);

        $this->actingAs($user)->get(route('matches.index'))
            ->assertOk()
            ->assertSee('アーセナル略')
            ->assertDontSee('Arsenal FC');
    }

    public function test_match_index_dropdown_falls_back_to_english_competition_name(): void
    {
        $this->createFootballMatch();

        $this->actingAs(User::factory()->create())->get(route('matches.index'))
            ->assertOk()
            ->assertSee('Premier League');
    }

    public function test_match_card_still_shows_tla_first(): void
    {
        $match = $this->createJapaneseMatch();
        $match->homeTeam->update(['tla' => 'ARS']);
        $match->awayTeam->update(['tla' => null, 'short_name' => null, 'short_name_ja' => 'チェルシー略']);

        $this->actingAs(User::factory()->create())->get(route('matches.index'))
            ->assertOk()
            ->assertSee('ARS')
            // tla がないチームは日本語の略称にフォールバックする
            ->assertSee('チェルシー略');
    }

    public function test_favorite_team_selection_displays_japanese_names(): void
    {
        $this->createJapaneseMatch();

        $this->actingAs(User::factory()->create())->get('/select/teams')
            ->assertOk()
            ->assertSee('プレミアリーグ')
            ->assertSee('アーセナル略')
            ->assertSee('Chelsea FC');
    }

    public function test_mypage_displays_japanese_names(): void
    {
        $match = $this->createJapaneseMatch();
        $user = User::factory()->create();
        $user->favoriteTeams()->sync([$match->home_team_id]);
        $reservation = $this->createReservationFor($match, $user);

        $this->actingAs($user)->get(route('mypage'))
            ->assertOk()
            ->assertSee('アーセナル vs Chelsea FC')
            ->assertDontSee('Arsenal FC')
            ->assertSee($reservation->reservation_code);
    }

    public function test_reservation_show_displays_japanese_names(): void
    {
        $match = $this->createJapaneseMatch();
        $user = User::factory()->create();
        $reservation = $this->createReservationFor($match, $user);

        $this->actingAs($user)->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('アーセナル vs Chelsea FC');
    }

    public function test_shop_show_displays_japanese_names(): void
    {
        $match = $this->createJapaneseMatch();
        $reservation = $this->createReservationFor($match, User::factory()->create());
        $shop = $reservation->broadcastSeatType->broadcast->shop;

        $this->actingAs(User::factory()->create())->get(route('shops.show', $shop))
            ->assertOk()
            ->assertSee('プレミアリーグ')
            ->assertSee('アーセナル vs Chelsea FC');
    }

    public function test_match_search_form_displays_japanese_competition_name(): void
    {
        $this->createJapaneseMatch();

        $this->actingAs(User::factory()->create())->get(route('matches.index', ['team' => 'Chelsea']))
            ->assertOk()
            ->assertSee('プレミアリーグ')
            ->assertDontSee('Premier League');
    }

    public function test_owner_reservation_list_displays_japanese_team_names(): void
    {
        $match = $this->createJapaneseMatch();
        $reservation = $this->createReservationFor($match, User::factory()->create());
        $shop = $reservation->broadcastSeatType->broadcast->shop;
        $owner = User::factory()->shopOwner()->create();
        $shop->update(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('owner.reservations.index', ['favorite' => 0]))
            ->assertOk()
            ->assertSee('アーセナル vs Chelsea FC')
            ->assertDontSee('Arsenal FC');
    }

    public function test_sync_teams_and_competitions_keep_japanese_names(): void
    {
        config([
            'services.football_data.base_url' => 'https://api.football-data.org/v4',
            'services.football_data.api_key' => 'test-api-key',
        ]);
        $competition = Competition::create([
            'external_competition_id' => 2021,
            'code' => 'PL',
            'name' => 'Old Name',
            'name_ja' => 'プレミアリーグ',
            'area_name' => 'England',
            'is_active' => true,
        ]);
        $team = Team::create([
            'external_team_id' => 57,
            'name' => 'Old Team',
            'name_ja' => 'アーセナル',
            'short_name_ja' => 'アーセナル略',
        ]);

        Http::fake([
            '*/competitions/PL/teams' => Http::response(['teams' => [[
                'id' => 57, 'name' => 'Arsenal FC', 'shortName' => 'Arsenal', 'tla' => 'ARS',
                'crest' => 'https://crests.football-data.org/57.png', 'area' => ['name' => 'England'],
            ]]]),
            '*/competitions' => Http::response(['competitions' => [[
                'id' => 2021, 'code' => 'PL', 'type' => 'LEAGUE', 'name' => 'Premier League',
                'area' => ['name' => 'England'], 'emblem' => 'https://crests.football-data.org/PL.png',
            ]]]),
        ]);

        $this->artisan('app:sync-competitions')->assertSuccessful();
        $this->artisan('app:sync-teams')->assertSuccessful();

        // 英語名は更新され、日本語名は残る
        $this->assertSame('Premier League', $competition->fresh()->name);
        $this->assertSame('プレミアリーグ', $competition->fresh()->name_ja);
        $this->assertSame('Arsenal FC', $team->fresh()->name);
        $this->assertSame('アーセナル', $team->fresh()->name_ja);
        $this->assertSame('アーセナル略', $team->fresh()->short_name_ja);
    }

    private function createReservationFor(FootballMatch $match, User $user): Reservation
    {
        $shop = Shop::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => Shop::STATUS_PUBLISHED,
        ]);
        $broadcast = Broadcast::factory()->forMatch($match)->create([
            'shop_id' => $shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
        ]);
        $seatType = SeatType::factory()->create(['shop_id' => $shop->id, 'name' => 'カウンター席', 'default_capacity' => 10, 'default_price' => 3000]);
        $broadcastSeatType = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);

        return Reservation::factory()->create([
            'broadcast_seat_type_id' => $broadcastSeatType->id,
            'user_id' => $user->id,
        ]);
    }
}
