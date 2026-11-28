<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'email_verified' => $this->hasVerifiedEmail(),
            'wallet_address' => $this->wallet_address,
            'wallet_linked_at' => $this->wallet_linked_at?->toIso8601String(),
            'organization_status' => $this->whenLoaded('organization', fn () => $this->organization?->status->value),
            'suspended_reason' => $this->when($request->user()?->isAdmin(), $this->suspended_reason),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
