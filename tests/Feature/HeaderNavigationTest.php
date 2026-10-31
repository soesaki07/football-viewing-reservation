<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * レスポンス HTML から、スマホ用メニュー（details）の部分だけを取り出す。
     */
    private function mobileMenuHtml(string $html): string
    {
        $this->assertSame(1, preg_match('#<details class="relative sm:hidden">.*?</details>#s', $html, $matches), 'スマホ用メニューが見つかりません');

        return $matches[0];
    }

    public function test_logged_in_page_has_mobile_menu_with_all_items(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/matches');

        $response->assertOk();
        $menu = $this->mobileMenuHtml($response->getContent());
        $this->assertStringContainsString('<summary', $menu);
        $this->assertStringContainsString('aria-label="メニュー"', $menu);
        $this->assertStringContainsString('href="'.route('select.teams').'"', $menu);
        $this->assertStringContainsString('href="'.route('mypage').'"', $menu);
        // ログアウトは POST + CSRF のフォーム
        $this->assertStringContainsString('action="'.route('logout').'" method="POST"', $menu);
        $this->assertStringContainsString('name="_token"', $menu);
    }

    public function test_guest_page_has_no_mobile_menu(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('sm:hidden', false);
        $response->assertDontSee('aria-label="メニュー"', false);
        $response->assertDontSee('マイページ');
    }

    public function test_menu_hides_favorite_links_when_user_has_no_favorites(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/matches');

        $menu = $this->mobileMenuHtml($response->getContent());
        $this->assertStringNotContainsString('試合一覧', $menu);
        $this->assertStringNotContainsString('お気に入りの試合に戻る', $menu);
    }

    public function test_menu_shows_full_list_link_when_favorite_only_view(): void
    {
        $user = User::factory()->create();
        $user->favoriteTeams()->sync([Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC'])->id]);
        $this->actingAs($user);

        $response = $this->get('/matches');

        $menu = $this->mobileMenuHtml($response->getContent());
        $this->assertStringContainsString('試合一覧', $menu);
        $this->assertStringNotContainsString('お気に入りの試合に戻る', $menu);
        // パソコン幅のナビにも同じ項目がある
        $this->assertSame(2, substr_count($response->getContent(), '>試合一覧</a>'));
    }

    public function test_menu_shows_back_to_favorite_link_when_full_list_view(): void
    {
        $user = User::factory()->create();
        $user->favoriteTeams()->sync([Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC'])->id]);
        $this->actingAs($user);

        $response = $this->get('/matches?favorite=0');

        $menu = $this->mobileMenuHtml($response->getContent());
        $this->assertStringContainsString('お気に入りの試合に戻る', $menu);
        $this->assertStringNotContainsString('>試合一覧</a>', $menu);
        $this->assertSame(2, substr_count($response->getContent(), '>お気に入りの試合に戻る</a>'));
    }
}
