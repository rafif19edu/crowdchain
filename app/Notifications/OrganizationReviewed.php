<?php

namespace App\Notifications;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** FR-NT-02: verification result (email + in-app). */
class OrganizationReviewed extends Notification
{
    public function __construct(public readonly Organization $organization) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->organization->status === OrganizationStatus::Verified;

        $mail = (new MailMessage)
            ->subject($approved ? 'Your organisation is verified' : 'Your organisation application needs changes')
            ->greeting('Hello '.$notifiable->name.',');

        return $approved
            ? $mail->line($this->organization->name.' is now verified on CrowdChain. You can create campaigns.')
                ->action('Open dashboard', url('/dashboard'))
            : $mail->line('Your application for '.$this->organization->name.' was not approved.')
                ->line('Reason: '.$this->organization->rejection_reason)
                ->action('Edit and resubmit', url('/dashboard/organization'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'organization_reviewed',
            'organization_id' => $this->organization->ulid,
            'status' => $this->organization->status->value,
            'reason' => $this->organization->rejection_reason,
        ];
    }
}
