<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Internal bigint id + a public ULID used in URLs (decision T2), so ids in
 * links cannot be guessed by counting.
 */
trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function ($model) {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
