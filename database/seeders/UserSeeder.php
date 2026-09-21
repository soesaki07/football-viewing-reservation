<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminPassword = config('seeder.admin_password');

        // 未設定（null）だけでなく、空文字のままの場合も止める
        if (blank($adminPassword)) {
            throw new \RuntimeException('SEED_ADMIN_PASSWORD が .env に設定されていません。');
        }

        // 再実行しても重複しないよう、メールアドレスをキーに作成・更新する
        $admin = User::firstOrNew(['email' => 'admin@example.com']);
        $admin->forceFill([
            'name' => '管理者',
            'email_verified_at' => now(),
            'password' => $adminPassword,
            'date_of_birth' => '1990-01-01',
            'role' => 'admin',
        ])->save();

        User::factory(40)->create();
        User::factory(10)->shopOwner()->create();
    }
}
