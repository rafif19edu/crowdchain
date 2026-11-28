<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\DocumentVisibility;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Any uploaded file, private or public (docs/11 §2.4). */
class Document extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'type', 'disk', 'path', 'original_name', 'mime', 'size_bytes', 'sha256', 'visibility', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'visibility' => DocumentVisibility::class,
            'size_bytes' => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPublic(): bool
    {
        return $this->visibility === DocumentVisibility::Public;
    }
}
