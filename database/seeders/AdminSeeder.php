<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the single CrowdChain admin (D-12, Q68) from .env values.
 * There is deliberately no default password.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('crowdchain.admin');
        $config['email'] = strtolower((string) $config['email']);

        if (blank($config['email']) || blank($config['password'])) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding the admin.');
        }

        if (strlen($config['password']) < 12) {
            throw new RuntimeException('ADMIN_PASSWORD must be at least 12 characters.');
        }

        if (User::where('role', Role::Admin)->where('email', '!=', $config['email'])->exists()) {
            throw new RuntimeException('An admin with a different email already exists. CrowdChain has exactly one admin.');
        }

        $admin = User::firstOrNew(['email' => $config['email']]);
        $admin->name = $config['name'];
        $admin->password = $config['password'];
        $admin->role = Role::Admin;
        $admin->status = UserStatus::Active;
        $admin->email_verified_at ??= now();

        if (filled($config['wallet'])) {
            $admin->wallet_address = strtolower($config['wallet']);
            $admin->wallet_linked_at ??= now();
        }

        $admin->save();

        $this->command?->info("Admin ready: {$admin->email}");
    }
}
