<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only record of admin and system actions (FR-AU-01, NFR-SEC-14).
 * The application can create rows but never update or delete them.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'auditable_type', 'auditable_id', 'reason', 'tx_hash', 'metadata', 'ip_address'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
