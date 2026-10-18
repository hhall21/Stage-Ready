<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientInvitationNotification extends Notification
{
    public function __construct(public string $coachName, public string $acceptUrl) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your invitation to Stage-Ready')
            ->greeting('Your coaching starts here.')
            ->line($this->coachName.' has invited you to join Stage-Ready.')
            ->line('Accept your invitation to access your coaching workspace immediately.')
            ->action('Join Stage-Ready', $this->acceptUrl)
            ->line('This invitation expires in seven days. If you were not expecting it, you can ignore this email.');
    }
}
