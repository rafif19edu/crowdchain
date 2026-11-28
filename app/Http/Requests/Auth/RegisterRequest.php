<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(Role::registrable())],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['consent.accepted' => 'You must accept the privacy notice to register.'];
    }
}
