<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'taro@example.com']);

        $response = $this->post('/login', [
            'email' => 'taro@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('matches.index'));
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $response = $this->post('/login', [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::check());
    }

    public function test_logout_clears_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertFalse(Auth::check());
    }

    private function attempt(string $email, string $password = 'wrong-password', array $server = [])
    {
        return $this->withServerVariables($server)->post('/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function test_sixth_attempt_is_blocked_even_with_correct_password(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('taro@example.com')
                ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }

        $response = $this->attempt('taro@example.com', 'password');

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'ログインの試行回数が多すぎます。',
            session('errors')->first('email')
        );
        $this->assertMatchesRegularExpression('/\d+秒後にもう一度お試しください。/', session('errors')->first('email'));
        $response->assertSessionHasInput('email', 'taro@example.com');
        $this->assertFalse(Auth::check());
    }

    public function test_login_is_allowed_again_after_one_minute(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('taro@example.com');
        }
        $this->attempt('taro@example.com', 'password')->assertSessionHasErrors('email');
        $this->assertStringContainsString('ログインの試行回数が多すぎます。', session('errors')->first('email'));
        $this->assertFalse(Auth::check());

        $this->travel(61)->seconds();

        $this->attempt('taro@example.com', 'password')->assertRedirect(route('matches.index'));
        $this->assertTrue(Auth::check());
    }

    public function test_other_email_and_other_ip_are_not_affected(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);
        User::factory()->create(['email' => 'hanako@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('taro@example.com');
        }

        // 別の IP
        $this->attempt('taro@example.com', 'password', ['REMOTE_ADDR' => '203.0.113.9'])
            ->assertRedirect(route('matches.index'));
        $this->post('/logout');

        // 別のメールアドレス
        $this->attempt('hanako@example.com', 'password')->assertRedirect(route('matches.index'));
    }

    public function test_successful_login_resets_the_failure_count(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        for ($i = 0; $i < 4; $i++) {
            $this->attempt('taro@example.com');
        }
        $this->attempt('taro@example.com', 'password')->assertRedirect(route('matches.index'));
        $this->post('/logout');

        // リセットされているので、また5回まで失敗できる（5回目までは通常のメッセージ）
        for ($i = 0; $i < 5; $i++) {
            $this->attempt('taro@example.com')
                ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }
        $this->attempt('taro@example.com', 'password');
        $this->assertFalse(Auth::check());
    }

    public function test_email_case_is_counted_as_the_same_key(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        foreach (['taro@example.com', 'TARO@example.com', 'Taro@Example.com', 'taro@example.com', 'TARO@EXAMPLE.COM'] as $email) {
            $this->attempt($email);
        }

        $response = $this->attempt('Taro@example.com', 'password');
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('試行回数が多すぎます', $response->baseResponse->getSession()->get('errors')->first('email'));
        $this->assertFalse(Auth::check());
    }

    public function test_validation_errors_are_not_counted(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        for ($i = 0; $i < 10; $i++) {
            $this->attempt('not-an-email')->assertSessionHasErrors('email');
            $this->post('/login', ['email' => ['a@example.com'], 'password' => 'x'])->assertSessionHasErrors('email');
            $this->post('/login', ['email' => 'taro@example.com'])->assertSessionHasErrors('password');
        }

        $this->attempt('taro@example.com', 'password')->assertRedirect(route('matches.index'));
    }

    private function longEmail(int $labelLength, int $lastLabelLength): string
    {
        // 64文字のローカル部と、同じ長さのラベル3つ＋最後のラベルで長いメールアドレスを作る（RFC 上は有効）
        $label = str_repeat('b', $labelLength);

        return str_repeat('a', 64).'@'.$label.'.'.$label.'.'.$label.'.'.str_repeat('d', $lastLabelLength).'.com';
    }

    public function test_long_email_is_still_throttled(): void
    {
        // 247文字：ハッシュにしないと、キャッシュのキー（varchar 255）に収まらない長さ
        $email = $this->longEmail(55, 10);
        $this->assertSame(247, strlen($email));

        for ($i = 0; $i < 5; $i++) {
            $this->attempt($email)->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }

        $response = $this->attempt($email);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('ログインの試行回数が多すぎます。', $response->baseResponse->getSession()->get('errors')->first('email'));
    }

    public function test_email_longer_than_255_characters_is_a_validation_error(): void
    {
        // 257文字（RFC 上は有効なので、max:255 で弾かれる）
        $email = $this->longEmail(60, 5);
        $this->assertSame(257, strlen($email));

        $response = $this->attempt($email);
        $response->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('試行回数', $response->baseResponse->getSession()->get('errors')->first('email'));
        $this->assertStringNotContainsString('正しくありません', $response->baseResponse->getSession()->get('errors')->first('email'));
    }

    public function test_failures_are_counted_before_authentication(): void
    {
        // 同時の要求を再現するのは難しいため、順番を確かめる：
        // 認証が走る時点で、すでにこの要求の分が数えられている
        User::factory()->create(['email' => 'taro@example.com']);
        $key = sha1('taro@example.com|127.0.0.1');
        $countDuringAuth = null;

        // 認証の開始（Attempting）の時点での回数を記録する
        Event::listen(Attempting::class, function () use ($key, &$countDuringAuth) {
            $countDuringAuth = RateLimiter::attempts($key);
        });

        $this->attempt('taro@example.com');

        $this->assertSame(1, $countDuringAuth);
    }
}
