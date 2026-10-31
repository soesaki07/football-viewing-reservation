<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRoleDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_eloquentでroleを指定しないとuserになる(): void
    {
        $user = User::create([
            'name' => 'テスト',
            'email' => 'default-role@example.com',
            'password' => 'password',
            'date_of_birth' => '2000-01-01',
        ]);

        $this->assertSame('user', $user->refresh()->role);
    }

    public function test_一括代入でroleを渡しても反映されない(): void
    {
        $user = User::create([
            'name' => 'テスト',
            'email' => 'mass-assign-role@example.com',
            'password' => 'password',
            'date_of_birth' => '2000-01-01',
            'role' => 'admin',
        ]);

        $this->assertSame('user', $user->refresh()->role);
    }

    public function test_db_tableのinsertでroleを指定しないとuserになる(): void
    {
        DB::table('users')->insert([
            'name' => 'テスト',
            'email' => 'default-role-db@example.com',
            'password' => 'password',
            'date_of_birth' => '2000-01-01',
        ]);

        $this->assertSame('user', DB::table('users')->where('email', 'default-role-db@example.com')->value('role'));
    }

    public function test_roleを指定したユーザーの値は変わらない(): void
    {
        $owner = User::factory()->create(['role' => 'shop_owner']);

        $this->assertSame('shop_owner', $owner->refresh()->role);
    }

    public function test_roleのインデックスが残っている(): void
    {
        $this->assertTrue(Schema::hasIndex('users', ['role']));
    }
}
