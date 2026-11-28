<?php

use App\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Notifications\RegistrationExpiring;
use Illuminate\Support\Facades\Notification;

it('expires verified organisations past their expiry date', function () {
    Notification::fake();
    $organization = Organization::factory()->verified()->create(['expiry_date' => now()->subDay()->toDateString()]);

    $this->artisan('registrations:check-expiry')->assertSuccessful();

    expect($organization->fresh()->status)->toBe(OrganizationStatus::Expired)
        ->and(AuditLog::where('action', 'organization.expired')->whereNull('actor_id')->exists())->toBeTrue();
    Notification::assertSentTo($organization->user, RegistrationExpiring::class, fn ($n) => $n->expired);
});

it('reminds once, 30 days before expiry', function () {
    Notification::fake();
    $soon = Organization::factory()->verified()->create(['expiry_date' => now()->addDays(20)->toDateString()]);
    $later = Organization::factory()->verified()->create(['expiry_date' => now()->addDays(60)->toDateString()]);

    $this->artisan('registrations:check-expiry');
    $this->artisan('registrations:check-expiry');

    Notification::assertSentToTimes($soon->user, RegistrationExpiring::class, 1);
    Notification::assertNotSentTo($later->user, RegistrationExpiring::class);
    expect($soon->fresh()->status)->toBe(OrganizationStatus::Verified);
});
