<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class LinkWalletRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'signature' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{130}$/'],
            'nonce' => ['required', 'string', 'size:32'],
        ];
    }
}
