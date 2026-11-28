<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-AU-02 — read-only. */
class AuditLogController extends Controller
{
    /** GET /admin/audit-logs?action=&actor=&from=&to= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:40'],
            'actor' => ['nullable', 'string', 'size:26'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = AuditLog::query()
            ->with(['actor', 'auditable'])
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['actor'] ?? null, fn ($q, $ulid) => $q->whereIn('actor_id', User::where('ulid', $ulid)->select('id')))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);

        return ApiResponse::paginated($page, AuditLogResource::class);
    }
}
