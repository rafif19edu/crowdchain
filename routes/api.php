<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CrowdChain API v1  (docs/15-api-design.md)
|--------------------------------------------------------------------------
| Every route lives under /api/v1. Stages S1–S4 add their routes here.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');
});
