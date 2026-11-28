<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** FR-UM-15, FR-UM-16 — no hard delete (D-17). */
class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** GET /admin/users?q=&role=&status=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $users = User::query()
            ->with('organization')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? 20);

        return ApiResponse::paginated($users, UserResource::class);
    }

    /** GET /admin/users/{user} */
    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user->load('organization')));
    }

    /** POST /admin/users/{user}/suspend */
    public function suspend(ReasonRequest $request, User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages(['user' => 'The admin account cannot be suspended.']);
        }

        $user->forceFill(['status' => UserStatus::Suspended, 'suspended_reason' => $request->validated('reason')])->save();

        // End every open session of this user straight away.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit->record($request->user(), 'user.suspended', $user, $request->validated('reason'));

        return ApiResponse::success(new UserResource($user), 'User suspended.');
    }

    /** POST /admin/users/{user}/unsuspend */
    public function unsuspend(Request $request, User $user): JsonResponse
    {
        $user->forceFill(['status' => UserStatus::Active, 'suspended_reason' => null])->save();

        $this->audit->record($request->user(), 'user.unsuspended', $user);

        return ApiResponse::success(new UserResource($user), 'User reactivated.');
    }
}
