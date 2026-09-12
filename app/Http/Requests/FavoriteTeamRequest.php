<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class FavoriteTeamRequest extends FormRequest
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
            'team_ids' => ['array', 'max:3'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ];
    }

    #[Override]
    public function messages()
    {
        return [
            'team_ids.max' => 'お気に入りチームは３チームまでです。',
        ];
    }
}
