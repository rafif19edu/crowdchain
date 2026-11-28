<?php

namespace App\Console\Commands;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Notifications\RegistrationExpiring;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily (FR-UM-10, D-16):
 *  - verified organisations past their expiry date become "expired";
 *  - those expiring within 30 days get one reminder.
 * Pausing their campaigns is added in stage S2.
 */
#[Signature('registrations:check-expiry')]
#[Description('Expire organisations with an expired certificate and remind those expiring soon')]
class CheckRegistrationExpiry extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $today = now()->startOfDay();
        $expired = 0;
        $reminded = 0;

        Organization::query()
            ->where('status', OrganizationStatus::Verified)
            ->whereDate('expiry_date', '<', $today)
            ->with('user')
            ->each(function (Organization $organization) use ($audit, &$expired) {
                $organization->forceFill(['status' => OrganizationStatus::Expired])->save();
                $audit->record(null, 'organization.expired', $organization);
                $organization->user->notify(new RegistrationExpiring($organization, expired: true));
                $expired++;
            });

        Organization::query()
            ->where('status', OrganizationStatus::Verified)
            ->whereNull('expiry_reminder_sent_at')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(config('crowdchain.limits.expiry_reminder_days')))
            ->with('user')
            ->each(function (Organization $organization) use (&$reminded) {
                $organization->user->notify(new RegistrationExpiring($organization, expired: false));
                $organization->forceFill(['expiry_reminder_sent_at' => now()])->save();
                $reminded++;
            });

        $this->info("Expired: {$expired}. Reminders sent: {$reminded}.");

        return self::SUCCESS;
    }
}
