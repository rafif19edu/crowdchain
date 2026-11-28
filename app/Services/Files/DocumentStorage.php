<?php

namespace App\Services\Files;

use App\Enums\DocumentType;
use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploads on the documents disk (local in development, Cloudflare R2
 * in production — D-47, Q69) and records their SHA-256 hash.
 */
class DocumentStorage
{
    public function store(
        UploadedFile $file,
        Model $owner,
        DocumentType $type,
        DocumentVisibility $visibility,
        User $uploader,
    ): Document {
        $disk = config('filesystems.documents_disk');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs("documents/{$type->value}", Str::random(40).'.'.$extension, $disk);

        /** @var Document $document */
        $document = $owner->morphMany(Document::class, 'documentable')->create([
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'visibility' => $visibility,
            'uploaded_by' => $uploader->id,
        ]);

        return $document;
    }

    /** A short-lived signed link (NFR-SEC-06). */
    public function temporaryUrl(Document $document): string
    {
        return Storage::disk($document->disk)->temporaryUrl(
            $document->path,
            now()->addMinutes(config('crowdchain.uploads.signed_url_minutes')),
        );
    }
}
