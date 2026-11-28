<?php

use App\Enums\UserStatus;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

it('lists and filters users', function () {
    donor(['name' => 'Aisyah']);
    fundraiser(['name' => 'Hafiz']);

    $this->actingAs(admin())->getJson('/api/v1/admin/users?role=fundraiser')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Hafiz');

    $this->actingAs(admin())->getJson('/api/v1/admin/users?q=aisy')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Aisyah');
});

it('suspends a user, ends their sessions and audits it', function () {
    $admin = admin();
    $user = donor();
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$user->ulid}/suspend", ['reason' => 'Fake campaign reported'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(AuditLog::where('action', 'user.suspended')->value('reason'))->toBe('Fake campaign reported');
});

it('blocks a suspended user on their next request', function () {
    $user = donor();
    $user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->actingAs($user)->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account is suspended. Contact the CrowdChain admin.');
});

it('cannot suspend the admin, and requires a reason', function () {
    $admin = admin();

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$admin->ulid}/suspend", ['reason' => 'testing it'])
        ->assertUnprocessable();
    $this->actingAs($admin)->postJson('/api/v1/admin/users/'.donor()->ulid.'/suspend')
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('unsuspends a user', function () {
    $user = donor();
    $user->forceFill(['status' => UserStatus::Suspended, 'suspended_reason' => 'x'])->save();

    $this->actingAs(admin())->postJson("/api/v1/admin/users/{$user->ulid}/unsuspend")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});
