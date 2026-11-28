<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Writes the append-only audit trail (FR-AU-01). */
class AuditLogger
{
    /**
     * @param  User|null  $actor  null for actions taken by the scheduler
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        ?User $actor,
        string $action,
        ?Model $target = null,
        ?string $reason = null,
        ?string $txHash = null,
        ?array $metadata = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $target?->getMorphClass(),
            'auditable_id' => $target?->getKey(),
            'reason' => $reason,
            'tx_hash' => $txHash,
            'metadata' => $metadata,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
