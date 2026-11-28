<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GET /api/v1/health — lets the frontend, Postman and Heroku check that the
 * API and its database are reachable. Reveals no secrets.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'unreachable';
        }

        $healthy = $database === 'ok';

        return ApiResponse::success(
            data: [
                'status' => $healthy ? 'ok' : 'degraded',
                'database' => $database,
                'chain_id' => config('crowdchain.chain.chain_id'),
                'contract_configured' => filled(config('crowdchain.chain.contract_address')),
                'time' => now()->toIso8601String(),
            ],
            status: $healthy ? 200 : 503,
        );
    }
}
