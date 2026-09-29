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
 * exception. Most of this app's mail (notifications, password reset, ...) leaves
 * through the DEFAULT mailer, which `Mailer::alwaysTo()` intercepts. But
 * OutboundEmail (spec 0175, D-1) deliberately sends through a NAMED mailer,
 * `config('outbound_emails.mailer')`, so it can point at `microsoft-graph`
 * independently of whatever `mail.default` is -- a real send-to-a-real-customer
 * risk in staging if that name were left uncovered, since `Mailer::alwaysTo()`
 * is set per Mailer INSTANCE, not globally. Every distinct mailer name this app
 * actually sends through is redirected below, not just the default one.
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

        $recipient = trim($recipient);

        foreach ($this->mailerNames() as $name) {
            $this->redirectMailer($name, $recipient);
        }
    }

    /**
     * Every distinct mailer NAME this app sends through: the app's own
     * default plus OutboundEmail's own named mailer (D-1), which may
     * legitimately differ from the default -- deduplicated so a mailer used
     * for both is not resolved/redirected twice.
     *
     * @return array<int, string>
     */
    private function mailerNames(): array
    {
        return array_values(array_unique(array_filter([
            $this->mail->getDefaultDriver(),
            config('outbound_emails.mailer'),
        ], static fn ($name) => is_string($name) && $name !== '')));
    }

    /**
     * Resolving a mailer only BUILDS its transport object (e.g.
     * MicrosoftGraphTransport/GraphMailClient) -- no HTTP/network call
     * happens until an actual send -- so redirecting an unconfigured Graph
     * mailer here is safe. Still guarded: a misconfigured/unknown mailer
     * NAME (e.g. a typo in OUTBOUND_EMAIL_MAILER) must not take down
     * staging's boot -- the OTHER mailer's redirect must still apply.
     */
    private function redirectMailer(string $name, string $recipient): void
    {
        try {
            $this->mail->mailer($name)->alwaysTo($recipient);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
