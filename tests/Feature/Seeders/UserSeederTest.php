<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['seeder.admin_password' => 'test-admin-password']);
    }

    // ---- 正常系 ----

    public function test_user_factory_creates_general_user_by_default(): void
    {
        $this->assertSame('user', User::factory()->create()->role);
    }

    public function test_user_factory_shop_owner_state_creates_shop_owner(): void
    {
        $this->assertSame('shop_owner', User::factory()->shopOwner()->create()->role);
    }

    public function test_seeder_creates_admin_general_users_and_shop_owners(): void
    {
        (new UserSeeder)->run();

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('test-admin-password', $admin->password));

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame(40, User::where('role', 'user')->count());
        $this->assertSame(10, User::where('role', 'shop_owner')->count());
    }

    public function test_seeder_can_be_rerun_without_duplicating_admin(): void
    {
        (new UserSeeder)->run();

        config(['seeder.admin_password' => 'changed-password']);
        (new UserSeeder)->run();

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());

        // 再実行時は管理者のパスワードが .env の現在の値に更新される
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertTrue(Hash::check('changed-password', $admin->password));
    }

    // ---- 異常系（設定不備）----

    public function test_seeder_fails_when_admin_password_is_empty_string(): void
    {
        config(['seeder.admin_password' => '']);

        try {
            (new UserSeeder)->run();
            $this->fail('例外が投げられるはずです。');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SEED_ADMIN_PASSWORD', $e->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeder_fails_when_admin_password_is_null(): void
    {
        config(['seeder.admin_password' => null]);

        try {
            (new UserSeeder)->run();
            $this->fail('例外が投げられるはずです。');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SEED_ADMIN_PASSWORD', $e->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
