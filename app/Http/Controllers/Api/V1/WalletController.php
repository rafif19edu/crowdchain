<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\LinkWalletRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\WalletLinkService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-UM-13, FR-UM-14 */
class WalletController extends Controller
{
    public function __construct(private readonly WalletLinkService $wallets) {}

    /** POST /wallet/nonce — the exact message MetaMask will ask the user to sign. */
    public function nonce(Request $request): JsonResponse
    {
        $nonce = $this->wallets->issueNonce($request->user());

        return ApiResponse::success([
            'nonce' => $nonce->nonce,
            'message' => $nonce->message,
            'expires_at' => $nonce->expires_at->toIso8601String(),
        ], status: 201);
    }

    /** POST /wallet/link */
    public function link(LinkWalletRequest $request): JsonResponse
    {
        $user = $this->wallets->link(
            $request->user(),
            $request->validated('address'),
            $request->validated('signature'),
            $request->validated('nonce'),
        );

        return ApiResponse::success(new UserResource($user), 'Wallet linked.');
    }

    /** DELETE /wallet */
    public function unlink(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->wallets->unlink($request->user())), 'Wallet unlinked.');
    }
}
