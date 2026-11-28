<?php

use App\Services\Audit\AuditLogger;

it('is append-only', function () {
    $log = app(AuditLogger::class)->record(admin(), 'test.action', reason: 'why');

    expect(fn () => $log->update(['reason' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('lets the admin filter the log', function () {
    $admin = admin();
    app(AuditLogger::class)->record($admin, 'user.suspended', donor(), 'spam');
    app(AuditLogger::class)->record($admin, 'organization.approved');

    $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?action=user.suspended')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.reason', 'spam')
        ->assertJsonPath('data.0.actor.name', $admin->name);
});
