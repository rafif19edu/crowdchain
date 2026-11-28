<?php

use App\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Notifications\OrganizationReviewed;
use Illuminate\Support\Facades\Notification;

it('lists applications for the admin, oldest first', function () {
    Organization::factory()->create(['name' => 'Second']);
    Organization::factory()->create(['name' => 'First'])->forceFill(['submitted_at' => now()->subDay()])->save();

    $this->actingAs(admin())->getJson('/api/v1/admin/organizations?status=pending')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'First')
        ->assertJsonPath('meta.total', 2);
});

it('approves a pending application, audits it and notifies the fundraiser', function () {
    Notification::fake();
    $admin = admin();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/organizations/{$organization->ulid}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'verified');

    expect($organization->fresh()->verified_by)->toBe($admin->id)
        ->and(AuditLog::where('action', 'organization.approved')->where('actor_id', $admin->id)->exists())->toBeTrue();
    Notification::assertSentTo($organization->user, OrganizationReviewed::class);
});

it('rejects with a mandatory reason', function () {
    Notification::fake();
    $organization = Organization::factory()->create();

    $this->actingAs(admin())->postJson("/api/v1/admin/organizations/{$organization->ulid}/reject")
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->actingAs(admin())->postJson("/api/v1/admin/organizations/{$organization->ulid}/reject", ['reason' => 'Certificate number does not match.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Certificate number does not match.');

    expect(AuditLog::where('action', 'organization.rejected')->value('reason'))->toBe('Certificate number does not match.');
});

it('cannot review an application that is not pending', function () {
    $organization = Organization::factory()->verified()->create();

    $this->actingAs(admin())->postJson("/api/v1/admin/organizations/{$organization->ulid}/approve")
        ->assertUnprocessable();
    expect($organization->fresh()->status)->toBe(OrganizationStatus::Verified);
});
