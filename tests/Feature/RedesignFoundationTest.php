<?php

namespace Tests\Feature;

use App\Models\SeatType;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 画面刷新 PR1（共通の土台）：ヘッダー・アプリ名・共通のメッセージ部品・エラー画面
 */
class RedesignFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 利用者用レイアウトだけを使うページを描画する（未ログインで見られる画面にレイアウトを使うものがないため）。
     */
    private function renderAppLayout(): string
    {
        return (string) $this->blade("@extends('layouts.app')\n@section('title', 'テスト')\n@section('content')本文@endsection");
    }

    // ---- ヘッダー ----

    public function test_guest_header_has_login_and_register_only(): void
    {
        $html = $this->renderAppLayout();

        $this->assertStringContainsString('href="'.route('login').'"', $html);
        $this->assertStringContainsString('>ログイン</a>', $html);
        $this->assertStringContainsString('>新規登録</a>', $html);
        $this->assertStringNotContainsString('マイページ', $html);
        $this->assertStringNotContainsString('ログアウト', $html);
        $this->assertStringNotContainsString('お気に入り登録', $html);
    }

    public function test_logged_in_header_has_user_links_but_no_owner_links(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $response = $this->get('/mypage');

        $response->assertOk();
        $response->assertSee('>お気に入り登録</a>', false);
        $response->assertSee('>マイページ</a>', false);
        $response->assertSee('>ログアウト</button>', false);
        $response->assertDontSee('>ログイン</a>', false);
        $response->assertDontSee('>新規登録</a>', false);
        foreach (['店舗情報', '予約管理', '放映管理', '座席管理'] as $label) {
            $response->assertDontSee('>'.$label.'</a>', false);
        }
    }

    public function test_shop_owner_header_has_owner_links(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'shop_owner']));

        $response = $this->get('/mypage');

        foreach (['店舗情報', '予約管理', '放映管理', '座席管理'] as $label) {
            // パソコン幅とスマホ幅の2か所
            $this->assertSame(2, substr_count($response->getContent(), '>'.$label.'</a>'), $label);
        }
    }

    public function test_current_page_nav_is_marked_with_aria_current(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'shop_owner']));

        $mypage = $this->get('/mypage')->getContent();
        // マイページだけが現在地（パソコン幅とスマホ幅の2か所）
        $this->assertSame(2, substr_count($mypage, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>マイページ</a>#', $mypage);

        $owner = $this->get(route('owner.shops.index'))->getContent();
        $this->assertSame(2, substr_count($owner, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>店舗情報</a>#', $owner);
        $this->assertDoesNotMatchRegularExpression('#aria-current="page"[^>]*>マイページ</a>#', $owner);
    }

    public function test_match_list_link_is_highlighted_without_aria_current_when_favorite_only_view(): void
    {
        $user = User::factory()->create();
        $user->favoriteTeams()->sync([Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC'])->id]);
        $this->actingAs($user);

        $html = $this->get('/matches')->getContent();

        // 「試合一覧」（favorite=0 へのリンク）が、パソコン幅とスマホ幅の2か所で、強調の見た目になる
        $this->assertSame(2, preg_match_all('#<a href="'.preg_quote(route('matches.index', ['favorite' => 0]), '#').'" class="[^"]*bg-primary-container text-on-primary">試合一覧</a>#', $html));
        $this->assertStringNotContainsString('お気に入りの試合に戻る', $html);
        // 押すと別の表示に移るリンクなので、aria-current は付けない
        $this->assertStringNotContainsString('aria-current="page"', $html);
    }

    public function test_back_to_favorite_link_is_highlighted_without_aria_current_when_full_list_view(): void
    {
        $user = User::factory()->create();
        $user->favoriteTeams()->sync([Team::create(['external_team_id' => 1, 'name' => 'Arsenal FC'])->id]);
        $this->actingAs($user);

        $html = $this->get('/matches?favorite=0')->getContent();

        $this->assertSame(2, preg_match_all('#<a href="'.preg_quote(route('matches.index', ['favorite' => 1]), '#').'" class="[^"]*bg-primary-container text-on-primary">お気に入りの試合に戻る</a>#', $html));
        $this->assertStringNotContainsString('>試合一覧</a>', $html);
        $this->assertStringNotContainsString('aria-current="page"', $html);
    }

    public function test_alert_role_can_be_overridden(): void
    {
        $html = (string) $this->blade('<x-alert variant="error" role="status">案内</x-alert>');
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringNotContainsString('role="alert"', $html);
        $this->assertStringContainsString('data-alert="error"', $html);
    }

    public function test_inactive_seat_type_notice_is_error_colored_without_alert_role(): void
    {
        $owner = User::factory()->create(['role' => 'shop_owner']);
        $shop = Shop::factory()->create(['user_id' => $owner->id]);
        $seatType = SeatType::forceCreate(['shop_id' => $shop->id, 'name' => '席A', 'default_capacity' => 10, 'default_price' => 3000, 'is_active' => false]);
        $this->actingAs($owner);

        $response = $this->get(route('owner.seat-types.edit', $seatType));

        $response->assertOk();
        $response->assertSee('この座席種別は無効です');
        $response->assertSee('data-alert="error"', false);
        $response->assertDontSee('role="alert"', false);
    }

    // ---- ボタンの href ----

    public function test_button_href_rejects_dangerous_schemes(): void
    {
        foreach (['javascript:alert(1)', 'JavaScript:alert(1)', ' javascript:alert(1)', "java\nscript:alert(1)", 'data:text/html,<b>x</b>', 'vbscript:x', '//evil.example.com', 'mailto:a@example.com', '/\\evil.example.com', '%6Aavascript:alert(1)', "java\tscript:alert(1)"] as $href) {
            $html = (string) $this->blade('<x-button :href="$href">x</x-button>', ['href' => $href]);

            $this->assertStringNotContainsString('href=', $html, $href);
            $this->assertStringNotContainsString('javascript', strtolower($html), $href);
            $this->assertStringContainsString('<a ', $html);
        }
    }

    public function test_button_href_entity_tricks_stay_escaped(): void
    {
        // 検査は通るが、{{ }} のエスケープで & が &amp; になり、実体参照として解釈されない。{{ }} を外すと落ちる
        foreach (['&#106;avascript:alert(1)', 'javascript&colon;alert(1)'] as $href) {
            $html = (string) $this->blade('<x-button :href="$href">x</x-button>', ['href' => $href]);

            $this->assertStringContainsString('href="'.str_replace('&', '&amp;', $href).'"', $html, $href);
            $this->assertStringNotContainsString('href="&#106;', $html);
            $this->assertStringNotContainsString('href="javascript&colon;', $html);
        }
    }

    public function test_button_href_accepts_http_https_and_relative_paths(): void
    {
        foreach (['https://example.com/a?b=1', 'http://example.com', 'HTTPS://example.com', '/matches', '/matches?favorite=0', 'matches/1', '?page=2', '#top'] as $href) {
            $html = (string) $this->blade('<x-button :href="$href">x</x-button>', ['href' => $href]);

            $this->assertStringContainsString('href="'.e($href).'"', $html, $href);
        }
    }

    public function test_error_page_back_button_has_no_inline_script_and_falls_back_to_link(): void
    {
        $html = $this->get('/no-such-page')->getContent();

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertMatchesRegularExpression('#<a\s+href="'.preg_quote(url('/'), '#').'"\s+class="[^"]*"\s+data-history-back="data-history-back">前のページへ戻る</a>#', $html);
    }

    // ---- ページの見出し・入力欄 ----

    public function test_page_header_renders_optional_parts(): void
    {
        $full = (string) $this->blade('<x-page-header eyebrow="Partner" title="店舗情報管理" description="説明文"><a href="/x">新規登録</a></x-page-header>');
        $this->assertStringContainsString('<h1', $full);
        $this->assertStringContainsString('>店舗情報管理</h1>', $full);
        $this->assertStringContainsString('Partner', $full);
        $this->assertStringContainsString('説明文', $full);
        $this->assertStringContainsString('<a href="/x">新規登録</a>', $full);

        $minimal = (string) $this->blade('<x-page-header title="試合一覧" />');
        $this->assertStringContainsString('>試合一覧</h1>', $minimal);
        $this->assertStringNotContainsString('<p', $minimal);
    }

    public function test_form_field_links_label_and_input_and_has_no_aria_without_error(): void
    {
        $html = (string) $this->blade('<x-form.field name="email" label="メールアドレス" type="email" hint="連絡用" required />');

        $this->assertStringContainsString('<label for="email"', $html);
        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('aria-describedby="email-hint"', $html);
        $this->assertStringContainsString('id="email-hint"', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
    }

    public function test_form_field_shows_error_with_aria_attributes_and_escapes_it(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['<b>不正</b>なメールです']]));
        $html = (string) $this->withViewErrors($errors->getBag('default')->getMessages())->blade('<x-form.field name="email" label="メール" hint="連絡用" />');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-error email-hint"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
        $this->assertStringContainsString('&lt;b&gt;不正&lt;/b&gt;なメールです', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_form_field_supports_select_textarea_and_array_names(): void
    {
        $select = (string) $this->blade('<x-form.field name="status" label="状態" type="select"><option value="a">A</option></x-form.field>');
        $this->assertStringContainsString('<select', $select);
        $this->assertStringContainsString('<option value="a">A</option>', $select);

        $textarea = (string) $this->blade('<x-form.field name="note" label="備考" type="textarea" value="メモ" rows="3" />');
        $this->assertStringContainsString('<textarea', $textarea);
        $this->assertStringContainsString('>メモ</textarea>', $textarea);

        $errors = (new MessageBag(['seats.1.price' => ['価格エラー']]))->getMessages();
        $array = (string) $this->withViewErrors($errors)->blade('<x-form.field name="seats[1][price]" label="価格" type="number" />');
        $this->assertStringContainsString('for="seats_1_price"', $array);
        $this->assertStringContainsString('id="seats_1_price"', $array);
        $this->assertStringContainsString('価格エラー', $array);
        $this->assertStringContainsString('aria-invalid="true"', $array);
    }

    public function test_page_header_omits_actions_wrapper_when_slot_is_only_whitespace(): void
    {
        $html = (string) $this->blade("<x-page-header title=\"試合一覧\">\n    \n</x-page-header>");

        $this->assertStringNotContainsString('flex-wrap items-center gap-3', $html);
    }

    public function test_register_keeps_old_email_but_never_password_after_validation_error(): void
    {
        $page = $this->from('/register')->followingRedirects()->post('/register', [
            'name' => '',
            'email' => 'old@example.com',
            'date_of_birth' => '2000-01-01',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ]);

        $page->assertSee('value="old@example.com"', false);
        $page->assertSee('value="2000-01-01"', false);
        $page->assertDontSee('secret-pass-123');
        $page->assertSee('aria-invalid="true"', false);
    }

    public function test_form_field_uses_old_input_for_array_names(): void
    {
        // 直前のリクエストで入力された値（old input）がある状態にする
        $this->withSession(['_old_input' => ['seats' => [1 => ['price' => '4500']]]]);
        request()->setLaravelSession(app('session.store'));

        $html = (string) $this->blade('<x-form.field name="seats[1][price]" label="価格" type="number" value="1000" />');

        $this->assertStringContainsString('value="4500"', $html);
        $this->assertStringNotContainsString('value="1000"', $html);
    }

    public function test_form_field_select_does_not_use_value_and_renders_slot_as_is(): void
    {
        $html = (string) $this->blade('<x-form.field name="status" label="状態" type="select" value="b"><option value="a" @selected(true)>A</option><option value="b">B</option></x-form.field>');

        $this->assertStringNotContainsString('value="b" selected', $html);
        $this->assertSame(1, substr_count($html, 'selected'));
        $this->assertStringNotContainsString(' value="b" id=', $html);
    }

    public function test_form_field_never_prints_password_value(): void
    {
        $html = (string) $this->blade('<x-form.field name="password" label="パスワード" type="password" value="secret" />');

        $this->assertStringNotContainsString('secret', $html);
        $this->assertStringContainsString('type="password"', $html);
    }

    public function test_login_fields_have_label_for_and_invalid_state_without_duplicate_message(): void
    {
        $page = $this->from('/login')->followingRedirects()->post('/login', ['email' => 'bad', 'password' => '']);

        $page->assertSee('<label for="email"', false);
        $page->assertSee('<label for="password"', false);
        $page->assertSee('aria-invalid="true"', false);
        // 欄の下には出さず、上のメッセージ部品にだけ出す（二重に出さない）
        $page->assertDontSee('id="email-error"', false);
        $this->assertSame(1, substr_count($page->getContent(), 'The email field must be a valid email address.'));
    }

    // ---- アプリ名 ----

    public function test_app_name_is_pitch_and_parlour_and_old_name_is_gone(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/mypage')->getContent();

        $this->assertStringNotContainsString('欧州サッカー観戦予約', $html);
        $this->assertStringContainsString('<title>マイページ - PITCH &amp; PARLOUR</title>', $html);
        $this->assertStringContainsString('Sports Gastropub &amp; Lounge', $html);
        $this->assertStringContainsString('&copy; '.date('Y').' PITCH &amp; PARLOUR', $html);
        // ロゴは試合一覧（/）へのリンク
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(url('/'), '#').'"[^>]*>\s*<span[^>]*>PITCH &amp; PARLOUR#', $html);
    }

    public function test_login_and_register_use_new_app_name(): void
    {
        foreach (['/login', '/register'] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('PITCH &amp; PARLOUR', false);
            $response->assertDontSee('欧州サッカー観戦予約');
            // 認証用レイアウト：ナビなし
            $response->assertDontSee('data-testid="desktop-nav"', false);
        }
    }

    // ---- メッセージの部品 ----

    public function test_success_message_is_rendered_with_alert_component(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->withSession(['success' => '登録しました。'])->get('/matches');

        $response->assertSee('data-alert="success"', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('登録しました。');
    }

    public function test_error_message_is_rendered_with_alert_component(): void
    {
        $owner = User::factory()->create(['role' => 'shop_owner']);
        Shop::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner);

        $response = $this->withSession(['error' => '失敗しました。'])->get(route('owner.broadcasts.index'));

        $response->assertSee('data-alert="error"', false);
        $response->assertSee('role="alert"', false);
        $response->assertSee('失敗しました。');
    }

    public function test_validation_errors_are_rendered_with_alert_component(): void
    {
        $page = $this->from('/login')->followingRedirects()->post('/login', ['email' => 'bad', 'password' => '']);

        $page->assertSee('data-alert="error"', false);
        // 実際の入力エラーの文言（メールアドレスの形式）が、部品の中の箇条書きに出る
        $this->assertSame(1, preg_match('#data-alert="error".*?<ul[^>]*>(.*?)</ul>#s', $page->getContent(), $matches));
        $this->assertStringContainsString('<li>'.e(trans('validation.email', ['attribute' => 'email'])).'</li>', $matches[1]);
    }

    public function test_alert_component_escapes_script_in_slot_and_messages(): void
    {
        $slot = (string) $this->blade('<x-alert variant="error">{{ $text }}</x-alert>', ['text' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $slot);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $slot);

        $list = (string) $this->blade('<x-alert variant="error" :messages="$messages" />', ['messages' => ['<script>alert(2)</script>']]);
        $this->assertStringNotContainsString('<script>', $list);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $list);
    }

    public function test_badge_component_changes_color_by_variant(): void
    {
        $success = (string) $this->blade('<x-badge variant="success">公開</x-badge>');
        $neutral = (string) $this->blade('<x-badge variant="neutral">非公開</x-badge>');

        $this->assertStringContainsString('>公開</span>', $success);
        $this->assertStringContainsString('bg-tertiary-fixed text-on-tertiary-fixed', $success);
        $this->assertStringNotContainsString('bg-surface-container-high', $success);
        $this->assertStringContainsString('>非公開</span>', $neutral);
        $this->assertStringContainsString('bg-surface-container-high text-on-surface-variant', $neutral);
        $this->assertStringNotContainsString('bg-tertiary-fixed', $neutral);
    }

    // ---- エラー画面 ----

    /**
     * @return array<string, array{int, string}>
     */
    public static function errorPages(): array
    {
        return [
            '403' => [403, 'アクセス権限がありません'],
            '404' => [404, 'お探しのページが見つかりませんでした'],
            '429' => [429, 'アクセスが集中しています。しばらく待ってからもう一度お試しください。'],
        ];
    }

    #[DataProvider('errorPages')]
    public function test_error_pages_render_in_japanese_for_guest_and_logged_in_user(int $status, string $text): void
    {
        Route::get('/_test-error/'.$status, fn () => abort($status, '内部の秘密メッセージ'));

        foreach ([false, true] as $loggedIn) {
            if ($loggedIn) {
                $this->actingAs(User::factory()->create());
            }
            $response = $this->get('/_test-error/'.$status);

            $response->assertStatus($status);
            $response->assertSee($text);
            $response->assertSee('試合一覧へ戻る');
            $response->assertSee('PITCH &amp; PARLOUR', false);
            // 内部の情報は出さない。ナビ（ログイン状態）にも依存しない
            $response->assertDontSee('内部の秘密メッセージ');
            $response->assertDontSee('Stack trace');
            $response->assertDontSee('vendor/laravel');
            $response->assertDontSee('data-testid="desktop-nav"', false);
            $response->assertDontSee('マイページ');
        }
    }

    public function test_404_page_is_rendered_for_unknown_url(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee('お探しのページが見つかりませんでした');
    }

    public function test_403_page_is_rendered_for_non_owner_on_owner_area(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $response = $this->get(route('owner.shops.index'));

        $response->assertForbidden();
        $response->assertSee('アクセス権限がありません');
    }
}
