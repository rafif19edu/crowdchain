<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full organisation record, for its owner and the admin only.
 *
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'registration_authority' => $this->registration_authority->value,
            'main_state' => $this->main_state,
            'name' => $this->name,
            'registration_no' => $this->registration_no,
            'registration_date' => $this->registration_date->toDateString(),
            'expiry_date' => $this->expiry_date->toDateString(),
            'institution_category' => $this->institution_category->value,
            'ros_ssm_no' => $this->ros_ssm_no,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'district' => $this->district,
            'state' => $this->state,
            'phone' => $this->phone,
            'email' => $this->email,
            'beneficiaries_count' => $this->beneficiaries_count,
            'bank' => [
                'name' => $this->bank_name,
                'account_no' => $this->bank_account_no,
                'account_holder' => $this->bank_account_holder,
                'holder_matches_name' => $this->bank_holder_matches,
            ],
            'pic' => [
                'name' => $this->pic_name,
                'ic_no' => $this->pic_ic_no,
                'position' => $this->pic_position,
                'phone' => $this->pic_phone,
                'email' => $this->pic_email,
            ],
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'owner' => new UserResource($this->whenLoaded('user')),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
