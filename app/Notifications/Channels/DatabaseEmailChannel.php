<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

class DatabaseEmailChannel
{
    /**
     * Send the given notification.
     *
     * This channel delegates to the notification's toDatabaseEmail method,
     * which uses the EmailConfigurationService to send emails via
     * admin-configured providers (Mailjet, SMTP, etc.).
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (method_exists($notification, 'toDatabaseEmail')) {
            $notification->toDatabaseEmail($notifiable);
        }
    }
}
