<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CrowdChain API v1  (docs/15-api-design.md)
|--------------------------------------------------------------------------
| Gates (docs/16 §2): auth:sanctum → not-suspended → verified → role → Policy.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    // --- Public -----------------------------------------------------------
    Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    Route::get('documents/{document}/url', [DocumentController::class, 'url'])->name('documents.url');
    Route::get('auth/email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    // --- Authentication ---------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('register', RegisterController::class)->middleware('throttle:10,1')->name('register');
        Route::post('login', [SessionController::class, 'store'])->name('login');
        Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.store');
    });

    // --- Logged in --------------------------------------------------------
    Route::middleware(['auth:sanctum', 'not-suspended'])->group(function () {
        Route::post('auth/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::get('auth/me', [SessionController::class, 'show'])->name('me');
        Route::post('auth/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')->name('verification.send');

        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        Route::middleware('verified-email')->group(function () {
            Route::post('wallet/nonce', [WalletController::class, 'nonce'])->middleware('throttle:10,1')->name('wallet.nonce');
            Route::post('wallet/link', [WalletController::class, 'link'])->middleware('throttle:10,1')->name('wallet.link');
            Route::delete('wallet', [WalletController::class, 'unlink'])->name('wallet.unlink');

            Route::middleware('role:fundraiser')->group(function () {
                Route::get('organization', [OrganizationController::class, 'mine'])->name('organization.mine');
                Route::post('organization', [OrganizationController::class, 'submit'])->name('organization.submit');
                Route::post('organization/renewal', [OrganizationController::class, 'renew'])->name('organization.renew');
            });
        });

        // --- Admin --------------------------------------------------------
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
            Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/unsuspend', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');

            Route::get('organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
            Route::get('organizations/{organization}', [AdminOrganizationController::class, 'show'])->name('organizations.show');
            Route::post('organizations/{organization}/approve', [AdminOrganizationController::class, 'approve'])->name('organizations.approve');
            Route::post('organizations/{organization}/reject', [AdminOrganizationController::class, 'reject'])->name('organizations.reject');

            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });
    });
});
