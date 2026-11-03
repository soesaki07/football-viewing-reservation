<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateShopOwnerTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-pass-123';

    public function test_既存のユーザーを確認でyesにするとshop_ownerになる(): void
    {
        $verifiedAt = Carbon::parse('2026-01-02 03:04:05');
        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => $verifiedAt]);

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', $user->email)
            ->expectsOutputToContain($user->name)
            ->expectsConfirmation('shop_owner にしますか？', 'yes')
            ->expectsOutputToContain("ID：{$user->id}")
            ->expectsOutputToContain("メールアドレス：{$user->email}")
            ->expectsOutputToContain('ロール：shop_owner')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertSame('shop_owner', $user->role);
        $this->assertTrue($user->email_verified_at->equalTo($verifiedAt));
    }

    public function test_メール未確認のユーザーには警告が出る(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'user', 'created_at' => Carbon::parse('2026-01-02 00:30:00', 'UTC')]);

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', $user->email)
            ->expectsOutputToContain('メールアドレスの確認：未確認')
            ->expectsOutputToContain('登録日時：2026-01-02 09:30（JST）')
            ->expectsOutputToContain('このアカウントのメールアドレスは本人確認されていません。')
            ->expectsConfirmation('shop_owner にしますか？', 'no')
            ->assertExitCode(0);
    }

    public function test_メール確認済みのユーザーには警告が出ない(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', $user->email)
            ->expectsOutputToContain('メールアドレスの確認：確認済み')
            ->doesntExpectOutputToContain('本人確認されていません')
            ->expectsConfirmation('shop_owner にしますか？', 'no')
            ->assertExitCode(0);
    }

    public function test_既存のユーザーを確認でnoにすると何も変わらない(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', $user->email)
            ->expectsConfirmation('shop_owner にしますか？', 'no')
            ->assertExitCode(0);

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_すでにshop_ownerなら何もせず成功で終わる(): void
    {
        $user = User::factory()->shopOwner()->create();

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', $user->email)
            ->expectsOutputToContain('すでに店舗オーナーです')
            ->assertExitCode(0);

        $this->assertSame('shop_owner', $user->fresh()->role);
    }

    public function test_adminはshop_ownerに変えられず失敗で終わる(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', $admin->email)
            ->expectsOutputToContain('運営者（admin）のアカウントは店舗オーナーに変更できません。')
            ->doesntExpectOutputToContain('shop_owner にしますか？')
            ->assertExitCode(1);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_メールアドレスが空なら失敗で終わる(): void
    {
        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', '')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_新しいshop_ownerを作れてパスワードでログインできる(): void
    {
        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', 'new-owner@example.com')
            ->expectsQuestion('名前', '新店長')
            ->expectsQuestion('生年月日（YYYY-MM-DD）', '1990-04-01')
            ->expectsQuestion('パスワード（8文字以上）', self::PASSWORD)
            ->expectsQuestion('パスワード（確認）', self::PASSWORD)
            ->expectsOutputToContain('new-owner@example.com')
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->assertExitCode(0);

        $user = User::where('email', 'new-owner@example.com')->firstOrFail();
        $this->assertSame('shop_owner', $user->role);
        $this->assertSame('新店長', $user->name);
        $this->assertNotNull($user->email_verified_at);
        // 平文のまま保存されず、1回だけハッシュ化されている
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        $this->post('/login', ['email' => 'new-owner@example.com', 'password' => self::PASSWORD])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    public static function invalidInputProvider(): array
    {
        $valid = ['new-owner@example.com', '新店長', '1990-04-01', self::PASSWORD, self::PASSWORD];

        return [
            'メールの形式' => ['not-an-email', $valid[1], $valid[2], $valid[3], $valid[4], 'メールアドレスのフォーマットが違います。'],
            '名前の欠け' => [$valid[0], '', $valid[2], $valid[3], $valid[4], 'ユーザーネームを入力してください。'],
            '生年月日の形式' => [$valid[0], $valid[1], '1990/04/01', $valid[3], $valid[4], '生年月日をYYYY-MM-DDの型で入力してください。'],
            '20歳未満' => [$valid[0], $valid[1], now()->subYears(19)->format('Y-m-d'), $valid[3], $valid[4], '20歳未満の方はご利用できません。'],
            '8文字未満' => [$valid[0], $valid[1], $valid[2], 'short12', 'short12', 'パスワードは８時以上で入力してください。'],
            '確認の不一致' => [$valid[0], $valid[1], $valid[2], self::PASSWORD, 'different-pass-1', 'パスワードが異なります。'],
        ];
    }

    #[DataProvider('invalidInputProvider')]
    public function test_入力のルールに合わないと作られず失敗で終わる(string $email, string $name, string $birth, string $password, string $confirmation, string $message): void
    {
        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', $email)
            ->expectsQuestion('名前', $name)
            ->expectsQuestion('生年月日（YYYY-MM-DD）', $birth)
            ->expectsQuestion('パスワード（8文字以上）', $password)
            ->expectsQuestion('パスワード（確認）', $confirmation)
            ->expectsOutputToContain($message)
            ->doesntExpectOutputToContain($password)
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_名前と生年月日の前後の空白は除かれて保存される(): void
    {
        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', 'trim@example.com')
            ->expectsQuestion('名前', '  新店長  ')
            ->expectsQuestion('生年月日（YYYY-MM-DD）', ' 1990-04-01 ')
            ->expectsQuestion('パスワード（8文字以上）', self::PASSWORD)
            ->expectsQuestion('パスワード（確認）', self::PASSWORD)
            ->assertExitCode(0);

        $user = User::where('email', 'trim@example.com')->firstOrFail();
        $this->assertSame('新店長', $user->name);
        $this->assertSame('1990-04-01', $user->date_of_birth);
    }

    public function test_検証のあとに同じメールが作られても500にならず失敗で終わる(): void
    {
        // 保存の直前に別のユーザーが同じメールで作られた状況を再現する
        User::creating(function (User $user) {
            DB::table('users')->insert([
                'name' => '先に作られたユーザー',
                'email' => $user->email,
                'password' => 'x',
                'role' => 'user',
                'date_of_birth' => '1990-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', 'race@example.com')
            ->expectsQuestion('名前', '新店長')
            ->expectsQuestion('生年月日（YYYY-MM-DD）', '1990-04-01')
            ->expectsQuestion('パスワード（8文字以上）', self::PASSWORD)
            ->expectsQuestion('パスワード（確認）', self::PASSWORD)
            ->expectsOutputToContain('このメールアドレスは登録済みです。')
            ->assertExitCode(1);

        $this->assertSame(1, User::where('email', 'race@example.com')->count());
        $this->assertSame('user', User::where('email', 'race@example.com')->value('role'));
    }

    public function test_保存で_uniqu_e違反以外の_d_bエラーが起きても汎用のメッセージで失敗で終わる(): void
    {
        // 例外のメッセージに、メールアドレスとパスワードのハッシュ（バインド値）を含めて再現する
        User::creating(function (User $user) {
            throw new QueryException('sqlite', 'insert into users (email, password) values (?, ?)', [$user->email, $user->password], new \RuntimeException('connection lost'));
        });

        $this->artisan('shop-owner:create')
            ->expectsQuestion('メールアドレス', 'dberror@example.com')
            ->expectsQuestion('名前', '新店長')
            ->expectsQuestion('生年月日（YYYY-MM-DD）', '1990-04-01')
            ->expectsQuestion('パスワード（8文字以上）', self::PASSWORD)
            ->expectsQuestion('パスワード（確認）', self::PASSWORD)
            ->expectsOutputToContain('アカウントを保存できませんでした。時間をおいてもう一度実行してください。')
            ->doesntExpectOutputToContain('dberror@example.com')
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->doesntExpectOutputToContain('$2y$')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }
}
