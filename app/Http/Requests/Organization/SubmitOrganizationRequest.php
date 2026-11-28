<?php

namespace App\Http\Requests\Organization;

use App\Enums\InstitutionCategory;
use App\Enums\RegistrationAuthority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** FR-UM-06: organisation, bank and person-in-charge details plus documents. */
class SubmitOrganizationRequest extends FormRequest
{
    public function rules(): array
    {
        $existing = $this->user()->organization()->first();
        $isNew = $existing === null;
        $states = config('crowdchain.states');
        $file = ['file', 'mimes:'.implode(',', config('crowdchain.uploads.mimes')), 'max:'.config('crowdchain.uploads.max_kb')];
        $requiredOnCreate = $isNew ? 'required' : 'nullable';

        return [
            'registration_authority' => ['required', Rule::enum(RegistrationAuthority::class)],
            'main_state' => ['nullable', 'required_if:registration_authority,main', 'prohibited_if:registration_authority,jkm', Rule::in($states)],
            'name' => ['required', 'string', 'max:200'],
            'registration_no' => [
                'required', 'string', 'max:60',
                Rule::unique('organizations')
                    ->where('registration_authority', $this->input('registration_authority'))
                    ->ignore($existing?->id),
            ],
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'institution_category' => ['required', Rule::enum(InstitutionCategory::class)],
            'ros_ssm_no' => ['nullable', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:500'],
            'postcode' => ['required', 'digits:5'],
            'district' => ['required', 'string', 'max:60'],
            'state' => ['required', Rule::in($states)],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'email' => ['required', 'email', 'max:190'],
            'beneficiaries_count' => ['required', 'integer', 'min:1', 'max:100000'],

            'bank_name' => ['required', 'string', 'max:60'],
            'bank_account_no' => ['required', 'digits_between:6,20'],
            'bank_account_holder' => ['required', 'string', 'max:200'],

            'pic_name' => ['required', 'string', 'max:120'],
            'pic_ic_no' => ['required', 'regex:/^\d{6}-?\d{2}-?\d{4}$/'],
            'pic_position' => ['required', 'string', 'max:80'],
            'pic_phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'pic_email' => ['required', 'email', 'max:190'],

            'registration_certificate' => [$requiredOnCreate, ...$file],
            'bank_proof' => [$requiredOnCreate, ...$file],
            'pic_letter' => [$requiredOnCreate, ...$file],
            'premises_photos' => [$requiredOnCreate, 'array', 'min:1', 'max:3'],
            'premises_photos.*' => ['image', 'mimes:jpg,jpeg,png', 'max:'.config('crowdchain.uploads.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_no.unique' => 'This registration number is already registered.',
            'pic_ic_no.regex' => 'Enter the IC number as 12 digits, e.g. 800101-10-1234.',
            'expiry_date.after' => 'The certificate has expired. Upload a valid certificate.',
        ];
    }
}
