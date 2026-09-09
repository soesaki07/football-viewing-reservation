<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'date_of_birth' => 'required|date_format:Y-m-d|before_or_equal:'.now()->subYears(20)->format('Y-m-d'),
            'password' => 'required|string|confirmed|min:8',
        ];
    }

    #[Override]
    public function messages()
    {
        return [
            'name.required' => 'ユーザーネームを入力してください。',
            'name.string' => 'ユーザーネームを文字列で入力してください。',
            'name.max' => 'ユーザーネームを255字以内で入力さてください。',
            'email.required' => 'メールアドレスと入力してください。',
            'email.email' => 'メールアドレスのフォーマットが違います。',
            'email.unique' => 'このメールアドレスは登録済みです。',
            'email.max' => 'メールアドレスを255字以内で入力してください。',
            'date_of_birth.required' => '生年月日を入力してください。',
            'date_of_birth.date_format' => '生年月日をYYYY-MM-DDの型で入力してください。',
            'date_of_birth.before_or_equal' => '20歳未満の方はご利用できません。',
            'password.required' => 'パスワードを入力してください。',
            'password.string' => '不正の文字列が入っています。',
            'password.confirmed' => 'パスワードが異なります。',
            'password.min' => 'パスワードは８時以上で入力してください。',
        ];
    }
}
