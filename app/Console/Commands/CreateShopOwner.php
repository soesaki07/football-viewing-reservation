<?php

namespace App\Console\Commands;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;

// パスワードを引数やオプションで渡す形は作らない（シェルの履歴に残るため）
#[Signature('shop-owner:create')]
#[Description('店舗オーナー（shop_owner）のアカウントを作る。既存のユーザーを shop_owner にすることもできる')]
class CreateShopOwner extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) $this->ask('メールアドレス'));

        if ($email === '') {
            $this->error('メールアドレスを入力してください。');

            return self::FAILURE;
        }

        // メールアドレスは登録画面・ログインと同じく、加工せずそのまま照合する
        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null) {
            return $this->promoteExistingUser($existingUser);
        }

        return $this->createNewUser($email);
    }

    /**
     * 既存のユーザーを shop_owner にする。
     */
    private function promoteExistingUser(User $user): int
    {
        // 運営者（admin）は変えない
        if ($user->role === 'admin') {
            $this->error('運営者（admin）のアカウントは店舗オーナーに変更できません。');

            return self::FAILURE;
        }

        if ($user->role === 'shop_owner') {
            $this->info('すでに店舗オーナーです。何も変更しませんでした。');

            return self::SUCCESS;
        }

        $this->line("名前：{$user->name}");
        $this->line("今のロール：{$user->role}");
        $this->line('メールアドレスの確認：'.($user->email_verified_at !== null ? '確認済み' : '未確認'));
        // 日時は DB 保存値（UTC）を、表示するときだけ JST に変換する
        $this->line('登録日時：'.$user->created_at?->copy()->setTimezone('Asia/Tokyo')->format('Y-m-d H:i').'（JST）');

        // 本人確認のないアカウントを、別人が先に登録している場合に備えて警告する
        if ($user->email_verified_at === null) {
            $this->warn('このアカウントのメールアドレスは本人確認されていません。店舗オーナー本人が登録したアカウントか、本人に確かめてから進めてください。');
        }

        if (! $this->confirm('shop_owner にしますか？')) {
            $this->info('何も変更せずに終了しました。');

            return self::SUCCESS;
        }

        // role は一括代入の対象外なので、明示的に設定する（email_verified_at は変えない）
        $user->forceFill(['role' => 'shop_owner'])->save();

        $this->showResult($user, '既存のユーザーを店舗オーナーにしました。');

        return self::SUCCESS;
    }

    /**
     * 新しい shop_owner のアカウントを作る。
     */
    private function createNewUser(string $email): int
    {
        $this->line('このメールアドレスのユーザーはいないので、新しく作ります。');

        $input = [
            // 登録画面（TrimStrings）に合わせて、名前と生年月日は前後の空白を除く（パスワードは除かない）
            'name' => trim((string) $this->ask('名前')),
            'email' => $email,
            'date_of_birth' => trim((string) $this->ask('生年月日（YYYY-MM-DD）')),
            'password' => (string) $this->secret('パスワード（8文字以上）'),
            'password_confirmation' => (string) $this->secret('パスワード（確認）'),
        ];

        // 登録画面と同じ入力のルール・メッセージを使う
        $request = new RegisterRequest;
        $validator = Validator::make($input, $request->rules(), $request->messages());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // ユーザーの作成とロールの設定を1回の保存で行う（role=user のアカウントを残さない）
        $user = new User;
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'date_of_birth' => $input['date_of_birth'],
            'password' => $input['password'], // User の cast（hashed）でハッシュ化される
            'role' => 'shop_owner',
            'email_verified_at' => now(),
        ]);

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            // 検証のあとに、同じメールアドレスのユーザーが作られた場合
            $this->error('このメールアドレスは登録済みです。');

            return self::FAILURE;
        } catch (QueryException) {
            // 例外のメッセージには SQL のバインド値（メール・パスワードのハッシュ）が入るため、出力しない
            $this->error('アカウントを保存できませんでした。時間をおいてもう一度実行してください。');

            return self::FAILURE;
        }

        $this->showResult($user, '店舗オーナーのアカウントを作りました。');

        return self::SUCCESS;
    }

    private function showResult(User $user, string $message): void
    {
        $this->info($message);
        $this->line("ID：{$user->id}");
        $this->line("名前：{$user->name}");
        $this->line("メールアドレス：{$user->email}");
        $this->line("ロール：{$user->role}");
    }
}
