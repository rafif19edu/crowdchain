<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => $this->actor ? ['id' => $this->actor->ulid, 'name' => $this->actor->name] : null,
            'target_type' => $this->auditable_type,
            'target_id' => $this->auditable?->ulid ?? $this->auditable_id,
            'reason' => $this->reason,
            'tx_hash' => $this->tx_hash,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
