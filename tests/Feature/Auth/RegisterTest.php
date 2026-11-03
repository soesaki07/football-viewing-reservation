<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'date_of_birth' => '2000-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_register_creates_user_and_logs_in(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $response->assertRedirect(route('select.teams'));
        $this->assertTrue(Auth::check());

        $user = User::where('email', 'taro@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_register_ignores_role_in_request(): void
    {
        $this->post('/register', $this->validPayload(['role' => 'admin']));

        $user = User::where('email', 'taro@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $response = $this->post('/register', $this->validPayload());

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'taro@example.com')->count());
    }

    public function test_register_fails_when_password_confirmation_does_not_match(): void
    {
        $response = $this->post('/register', $this->validPayload([
            'password_confirmation' => 'different-password',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertNull(User::where('email', 'taro@example.com')->first());
    }

    public function test_register_fails_for_underage_date_of_birth(): void
    {
        $response = $this->post('/register', $this->validPayload([
            'date_of_birth' => now()->subYears(19)->format('Y-m-d'),
        ]));

        $response->assertSessionHasErrors('date_of_birth');
        $this->assertNull(User::where('email', 'taro@example.com')->first());
    }

    public function test_sixth_register_request_from_same_ip_gets_429(): void
    {
        // 失敗（入力エラー）も回数に数える
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertSessionHasErrors('name');
        }

        $this->post('/register', $this->validPayload())
            ->assertStatus(429)
            ->assertSee('アクセスが集中しています。しばらく待ってからもう一度お試しください。');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_is_allowed_again_after_one_minute_and_other_ip_is_not_affected(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', []);
        }
        $this->post('/register', $this->validPayload())->assertStatus(429);

        // 別の IP は影響を受けない
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/register', $this->validPayload())
            ->assertRedirect(route('select.teams'));
        $this->post('/logout');

        $this->travel(61)->seconds();

        $this->post('/register', $this->validPayload(['email' => 'hanako@example.com']))
            ->assertRedirect(route('select.teams'));
    }
}
