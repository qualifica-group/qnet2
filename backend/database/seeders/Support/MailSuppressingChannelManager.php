<?php

namespace Database\Seeders\Support;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notification;

/**
 * The notification dispatcher a seed runs under: the in-app channels deliver
 * as usual, the mail channel is a no-op. Every notification is sent right
 * away, ignoring ShouldQueue: queued, the mail job would reach a worker that
 * resolves the real dispatcher and delivers it to the recipients.
 */
final class MailSuppressingChannelManager extends ChannelManager
{
    public function send($notifiables, $notification): void
    {
        $this->sendNow($notifiables, $notification);
    }

    protected function createMailDriver(): object
    {
        return new class
        {
            public function send(object $notifiable, Notification $notification): void {}
        };
    }
}
