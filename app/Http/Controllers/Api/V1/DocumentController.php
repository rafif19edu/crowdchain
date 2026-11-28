<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Files\DocumentStorage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    /** GET /documents/{document}/url — short-lived signed link (NFR-SEC-06). */
    public function url(Request $request, Document $document, DocumentStorage $storage): JsonResponse
    {
        // Private files are hidden as "not found" rather than "forbidden".
        abort_unless(Gate::forUser($request->user('sanctum'))->allows('view', $document), 404);

        return ApiResponse::success([
            'url' => $storage->temporaryUrl($document),
            'expires_in_minutes' => config('crowdchain.uploads.signed_url_minutes'),
            'sha256' => $document->sha256,
        ]);
    }
}
