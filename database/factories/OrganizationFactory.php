<?php

namespace Database\Factories;

use App\Enums\InstitutionCategory;
use App\Enums\OrganizationStatus;
use App\Enums\RegistrationAuthority;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Rumah Kasih '.fake()->unique()->lastName();

        return [
            'user_id' => User::factory()->fundraiser(),
            'registration_authority' => RegistrationAuthority::Jkm,
            'main_state' => null,
            'name' => $name,
            'registration_no' => 'JKM/'.fake()->unique()->numerify('SEL/####'),
            'registration_date' => now()->subYears(3)->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'institution_category' => InstitutionCategory::ChildrensHome,
            'ros_ssm_no' => null,
            'address' => 'No. 1, Jalan Harmoni, Taman Bahagia',
            'postcode' => '40000',
            'district' => 'Petaling',
            'state' => 'Selangor',
            'phone' => '03-55112233',
            'email' => fake()->unique()->safeEmail(),
            'beneficiaries_count' => 35,
            'bank_name' => 'Maybank',
            'bank_account_no' => '512345678901',
            'bank_account_holder' => strtoupper($name),
            'pic_name' => fake()->name(),
            'pic_ic_no' => '800101-10-1234',
            'pic_position' => 'Pengerusi',
            'pic_phone' => '012-3456789',
            'pic_email' => fake()->unique()->safeEmail(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Organization $organization) {
            $organization->status ??= OrganizationStatus::Pending;
            $organization->submitted_at ??= now();
            $organization->bank_holder_matches = Organization::namesMatch(
                $organization->name,
                $organization->bank_account_holder,
            );
        });
    }

    public function status(OrganizationStatus $status): static
    {
        return $this->afterMaking(fn (Organization $o) => $o->status = $status);
    }

    public function verified(): static
    {
        return $this->afterMaking(function (Organization $o) {
            $o->status = OrganizationStatus::Verified;
            $o->verified_at = now();
        });
    }
}
