<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    // 1分間に許す失敗の回数
    private const MAX_ATTEMPTS = 5;

    // 失敗の記録を残す秒数（この時間で回数が戻る）
    private const DECAY_SECONDS = 60;

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required',
        ]);

        // メールアドレス（小文字）と IP の組み合わせで数える。検証の後なので email は必ず文字列。
        // キャッシュのキーの長さに収まるよう、ハッシュにする
        $throttleKey = sha1(Str::lower($credentials['email']).'|'.$request->ip());

        // 認証（時間がかかる）の前に先に数える。同時に送られた要求が、どれも「まだ0回」として通り抜けるのを防ぐ
        $attempts = RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        // 5回失敗したあとの要求は、パスワードが正しくても認証せずに止める
        if ($attempts > self::MAX_ATTEMPTS) {
            event(new Lockout($request));

            return back()->withErrors([
                'email' => 'ログインの試行回数が多すぎます。'.RateLimiter::availableIn($throttleKey).'秒後にもう一度お試しください。',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('matches.index'));
        }

        return back()->withErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
