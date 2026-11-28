<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Donor,
            'status' => UserStatus::Active,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function donor(): static
    {
        return $this->state(fn () => ['role' => Role::Donor]);
    }

    public function fundraiser(): static
    {
        return $this->state(fn () => ['role' => Role::Fundraiser]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function suspended(string $reason = 'Test suspension'): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended, 'suspended_reason' => $reason]);
    }

    public function withWallet(?string $address = null): static
    {
        return $this->state(fn () => [
            'wallet_address' => strtolower($address ?? '0x'.bin2hex(random_bytes(20))),
            'wallet_linked_at' => now(),
        ]);
    }
}
