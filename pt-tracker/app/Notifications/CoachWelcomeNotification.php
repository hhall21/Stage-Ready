<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachWelcomeNotification extends Notification
{
    public function __construct(public string $setupUrl) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your Stage-Ready coach account is ready')
            ->greeting('Welcome to Stage-Ready')
            ->line('Your coach account is ready. Choose your own password to sign in.')
            ->action('Set your password', $this->setupUrl)
            ->line('After signing in, open Clients to invite your first client.')
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. If it expires, use Forgot your password on the sign-in page.');
    }
}
