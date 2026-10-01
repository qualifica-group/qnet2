<?php

namespace App\Mail;

use App\Exceptions\Mail\MailBlockedInStagingException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\Events\MessageSending;
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
 *
 * Fails closed in staging: APP_ENV=staging without MAIL_ALWAYS_TO blocks every
 * send instead of delivering to the real contacts (user decision 2026-10-01).
 */
final class StagingMailRedirector
{
    private const string STAGING_ENVIRONMENT = 'staging';

    public function __construct(
        private readonly Application $app,
        private readonly MailManager $mail,
        private readonly Dispatcher $events,
    ) {}

    public function handle(): void
    {
        // Step 1: production always delivers normally.
        if ($this->app->isProduction()) {
            return;
        }

        // Step 2: with a redirect mailbox, funnel every mailer to it.
        $recipient = $this->recipient();

        if ($recipient !== null) {
            foreach ($this->mailerNames() as $name) {
                $this->redirectMailer($name, $recipient);
            }

            return;
        }

        // Step 3: staging without a redirect mailbox sends nothing at all.
        if ($this->app->environment(self::STAGING_ENVIRONMENT)) {
            $this->blockAllMail();
        }
    }

    private function recipient(): ?string
    {
        $recipient = config('mail.always_to');

        return is_string($recipient) && trim($recipient) !== '' ? trim($recipient) : null;
    }

    /**
     * MessageSending is dispatched by every Mailer instance before its
     * transport runs, so this covers named mailers too. Throwing (rather than
     * returning false to cancel silently) surfaces the block: queued mail
     * jobs fail and SendOutboundEmailJob records the email as failed, not
     * sent.
     */
    private function blockAllMail(): void
    {
        $this->events->listen(MessageSending::class, static function (): never {
            throw new MailBlockedInStagingException;
        });
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
