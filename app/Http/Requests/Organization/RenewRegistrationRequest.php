<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class RenewRegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expiry_date' => ['required', 'date', 'after:today'],
            'registration_certificate' => [
                'required', 'file',
                'mimes:'.implode(',', config('crowdchain.uploads.mimes')),
                'max:'.config('crowdchain.uploads.max_kb'),
            ],
        ];
    }
}
