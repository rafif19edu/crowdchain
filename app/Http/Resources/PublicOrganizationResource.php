<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the public may see about an organisation (NFR-PRIV-02).
 *
 * @mixin Organization
 */
class PublicOrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'registration_authority' => $this->registration_authority->value,
            'main_state' => $this->main_state,
            'registration_no' => $this->registration_no,
            'expiry_date' => $this->expiry_date->toDateString(),
            'institution_category' => $this->institution_category->value,
            'state' => $this->state,
            'district' => $this->district,
            'beneficiaries_count' => $this->beneficiaries_count,
            'verified' => $this->isVerified(),
            'premises_photos' => DocumentResource::collection(
                $this->documents->where('type', DocumentType::PremisesPhoto)->values()
            ),
        ];
    }
}
