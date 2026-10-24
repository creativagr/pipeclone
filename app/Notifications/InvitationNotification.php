<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class InvitationNotification extends Notification
{
    /**
     * Number of days the invitation link stays valid.
     */
    public const int EXPIRES_IN_DAYS = 7;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(Invitation $invitation): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(Invitation $invitation): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'invitations.accept',
            now()->addDays(self::EXPIRES_IN_DAYS),
            ['invitation' => $invitation],
        );

        return (new MailMessage)
            ->subject(__('You are invited to join :organization', ['organization' => $invitation->organization->name]))
            ->line(__('You have been invited to join :organization.', ['organization' => $invitation->organization->name]))
            ->action(__('Accept invitation'), $url)
            ->line(__('This invitation expires in :days days.', ['days' => self::EXPIRES_IN_DAYS]));
    }
}
