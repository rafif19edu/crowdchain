<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** FR-UM-10: reminder 30 days before, and notice on expiry. */
class RegistrationExpiring extends Notification
{
    public function __construct(public readonly Organization $organization, public readonly bool $expired) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->organization->expiry_date->format('j M Y');

        return (new MailMessage)
            ->subject($this->expired ? 'Your registration has expired' : 'Your registration expires soon')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->expired
                ? "The registration certificate for {$this->organization->name} expired on {$date}. New campaigns are paused until you upload a renewed certificate."
                : "The registration certificate for {$this->organization->name} expires on {$date}. Upload the renewed certificate before then.")
            ->action('Upload renewed certificate', url('/dashboard/organization'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->expired ? 'registration_expired' : 'registration_expiring',
            'organization_id' => $this->organization->ulid,
            'expiry_date' => $this->organization->expiry_date->toDateString(),
        ];
    }
}
