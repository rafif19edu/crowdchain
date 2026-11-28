<?php

namespace App\Policies;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->isAdmin() || $organization->user_id === $user->id;
    }

    /** Edit/resubmit only while pending or rejected. */
    public function update(User $user, Organization $organization): bool
    {
        return $organization->user_id === $user->id
            && in_array($organization->status, [OrganizationStatus::Pending, OrganizationStatus::Rejected], true);
    }

    /** Upload a renewed certificate once verified or expired (FR-UM-12). */
    public function renew(User $user, Organization $organization): bool
    {
        return $organization->user_id === $user->id
            && in_array($organization->status, [OrganizationStatus::Verified, OrganizationStatus::Expired], true);
    }
}
