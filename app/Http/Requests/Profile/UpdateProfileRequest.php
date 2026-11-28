<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'email' => ['sometimes', 'required', 'lowercase', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->user()->id)],
        ];
    }
}
