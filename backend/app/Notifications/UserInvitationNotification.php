<?php

namespace App\Notifications;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly UserInvitation $invitation,
        private readonly string $plainToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = collect(explode(',', (string) config('app.frontend_url')))
            ->map(fn (string $url) => trim($url))
            ->filter()
            ->first() ?: config('app.url');

        $inviteUrl = rtrim($frontendUrl, '/').'/accept-invite?'.http_build_query([
            'token' => $this->plainToken,
            'email' => $this->invitation->email,
        ]);

        return (new MailMessage)
            ->subject('You have been invited to Fleet Management System')
            ->line('You have been invited to join a fleet management workspace.')
            ->action('Accept invitation', $inviteUrl)
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
