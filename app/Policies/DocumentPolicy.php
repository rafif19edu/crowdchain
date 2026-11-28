<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;

/** Public files: anyone. Private files: the owner and the admin (NFR-SEC-06). */
class DocumentPolicy
{
    public function view(?User $user, Document $document): bool
    {
        if ($document->isPublic()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $document->uploaded_by === $user->id) {
            return true;
        }

        $owner = $document->documentable;

        return $owner instanceof Organization && $owner->user_id === $user->id;
    }
}
