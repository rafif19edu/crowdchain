<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\Organization\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** FR-UM-08 */
class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    /** GET /admin/organizations?status= — oldest submissions first. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = Organization::query()
            ->with('user')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('submitted_at')
            ->paginate($filters['per_page'] ?? 20);

        return ApiResponse::paginated($page, OrganizationResource::class);
    }

    /** GET /admin/organizations/{organization} */
    public function show(Organization $organization): JsonResponse
    {
        return ApiResponse::success(new OrganizationResource($organization->load(['documents', 'user'])));
    }

    /** POST /admin/organizations/{organization}/approve */
    public function approve(Request $request, Organization $organization): JsonResponse
    {
        $organization = $this->organizations->approve($request->user(), $organization);

        return ApiResponse::success(new OrganizationResource($organization), 'Organisation verified.');
    }

    /** POST /admin/organizations/{organization}/reject */
    public function reject(ReasonRequest $request, Organization $organization): JsonResponse
    {
        $organization = $this->organizations->reject($request->user(), $organization, $request->validated('reason'));

        return ApiResponse::success(new OrganizationResource($organization), 'Application rejected.');
    }
}
