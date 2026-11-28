<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\AdminSeeder;

it('refuses to run without admin credentials', function () {
    config(['crowdchain.admin.email' => null, 'crowdchain.admin.password' => null]);

    expect(fn () => (new AdminSeeder)->run())->toThrow(RuntimeException::class, 'Set ADMIN_EMAIL');
});

it('creates exactly one verified admin and is safe to rerun', function () {
    config(['crowdchain.admin' => ['name' => 'Admin', 'email' => 'Admin@Example.test', 'password' => 'a-long-password-123', 'wallet' => '0xABCDEF0123456789abcdef0123456789ABCDEF01']]);

    (new AdminSeeder)->run();
    (new AdminSeeder)->run();

    $admin = User::where('role', Role::Admin)->sole();
    expect($admin->email)->toBe('admin@example.test')
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->wallet_address)->toBe('0xabcdef0123456789abcdef0123456789abcdef01');
});
