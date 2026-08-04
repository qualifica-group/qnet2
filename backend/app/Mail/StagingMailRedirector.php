<?php

namespace App\Mail;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;

/**
 * Funnels every outgoing email to the single mailbox in config('mail.always_to'),
 * dropping the original to/cc/bcc, so a staging deployment running on a copy of
 * production data can never reach real contacts.
 *
 * Applied once at boot rather than per send site: it must cover ALL mail without
 * exception, and every email in this app leaves through the notification `mail`
 * channel on the default mailer (there are no Mailables and no ->mailer() call),
 * which is exactly what Mailer::alwaysTo() intercepts.
 *
 * Hard-guarded against production even when the variable is set: the redirect is
 * a staging aid, and an operator who copies a staging .env onto the production
 * host must not silently divert real customer mail into a QA mailbox.
 */
final class StagingMailRedirector
{
    public function __construct(
        private readonly Application $app,
        private readonly MailManager $mail,
    ) {}

    public function handle(): void
    {
        $recipient = config('mail.always_to');

        if (! is_string($recipient) || trim($recipient) === '') {
            return;
        }

        if ($this->app->isProduction()) {
            return;
        }

        $this->mail->alwaysTo(trim($recipient));
    }
}
