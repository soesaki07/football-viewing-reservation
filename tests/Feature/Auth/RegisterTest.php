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
}
