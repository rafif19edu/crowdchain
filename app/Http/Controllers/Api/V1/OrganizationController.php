<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\RenewRegistrationRequest;
use App\Http\Requests\Organization\SubmitOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\PublicOrganizationResource;
use App\Models\Organization;
use App\Services\Organization\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-UM-06 to FR-UM-12 */
class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    /** GET /organization — the fundraiser's own application (or null). */
    public function mine(Request $request): JsonResponse
    {
        $organization = $request->user()->organization()->with('documents')->first();

        return ApiResponse::success($organization ? new OrganizationResource($organization) : null);
    }

    /** POST /organization — create, or edit & resubmit while pending/rejected. */
    public function submit(SubmitOrganizationRequest $request): JsonResponse
    {
        $existing = $request->user()->organization()->first();

        if ($existing) {
            $this->authorize('update', $existing);
        }

        $organization = $this->organizations->submit(
            $request->user(),
            $request->safe()->except(['registration_certificate', 'bank_proof', 'pic_letter', 'premises_photos']),
            $request->allFiles(),
        );

        return ApiResponse::success(
            new OrganizationResource($organization),
            'Submitted for verification.',
            $existing ? 200 : 201,
        );
    }

    /** POST /organization/renewal */
    public function renew(RenewRegistrationRequest $request): JsonResponse
    {
        $organization = $request->user()->organization()->first();
        abort_if(! $organization, 404);
        $this->authorize('renew', $organization);

        $organization = $this->organizations->renew(
            $request->user(),
            $organization,
            $request->validated('expiry_date'),
            $request->file('registration_certificate'),
        );

        return ApiResponse::success(new OrganizationResource($organization), 'Renewal submitted for verification.');
    }

    /** GET /organizations/{organization} — public profile of a verified organisation. */
    public function show(Organization $organization): JsonResponse
    {
        abort_unless(in_array($organization->status, [OrganizationStatus::Verified, OrganizationStatus::Expired], true), 404);

        return ApiResponse::success(new PublicOrganizationResource($organization->load('documents')));
    }
}
