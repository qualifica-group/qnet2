<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OutboundEmailStatus;
use App\Mail\OutboundEmailMessage;
use App\Models\OutboundEmail;
use App\Services\Graph\GraphMailException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers ONE OutboundEmail (spec 0175, D-12): `$tries = 1` on purpose -- a
 * Graph send repeated after a worker timeout would duplicate the email in
 * the recipient's inbox, so a failure is surfaced as `failed` +
 * `error_message` and the operator resends manually (D-2, D-14's
 * `work-orders.sendEmail`), never through Laravel's own automatic retry.
 *
 * Goes through `Mail::mailer(config('outbound_emails.mailer'))` (D-1)
 * rather than a bespoke Graph call, so `Mail::fake()` in tests and
 * `StagingMailRedirector`'s `MAIL_ALWAYS_TO` apply exactly as they do to
 * every other mailer in this app.
 */
class SendOutboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly OutboundEmail $email) {}

    public function handle(): void
    {
        // Step 1: idempotency guard -- a job that runs against an email no
        // longer `queued` (already sent/failed by a previous run, or a
        // stale draft never actually queued) does nothing.
        if ($this->email->status !== OutboundEmailStatus::Queued) {
            return;
        }

        try {
            // Step 2: hand off to the configured mailer.
            Mail::mailer(config('outbound_emails.mailer'))
                ->send(new OutboundEmailMessage($this->email));

            // Step 3: record the outcome.
            $this->email->markSent();
        } catch (Throwable $exception) {
            $this->recordFailure($exception);
        }
    }

    /**
     * Reached ONLY when the queue worker itself kills the job (timeout, OOM,
     * unhandled fatal) before handle()'s own try/catch can run -- handle()
     * never leaves an exception unhandled on its own, so this is purely a
     * worker-level safety net. Guarded so a race with an already-resolved
     * dispatch never clobbers a sent/failed row.
     */
    public function failed(?Throwable $exception): void
    {
        if ($this->email->status !== OutboundEmailStatus::Queued) {
            return;
        }

        $this->email->markFailed(__('outbound_emails.job_generic_failure'));

        Log::error('Outbound email job killed by the queue worker before completion.', [
            'outbound_email_id' => $this->email->id,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * A GraphMailException's message is ALREADY the safe, translated,
     * user-facing string (GraphMailException); any other throwable
     * (network blip, a `log`-mailer misconfiguration in dev, ...) falls back
     * to a generic translated message instead of leaking its own text.
     */
    private function recordFailure(Throwable $exception): void
    {
        $message = $exception instanceof GraphMailException
            ? $exception->getMessage()
            : __('outbound_emails.job_generic_failure');

        $this->email->markFailed($message);

        Log::error('Failed to send outbound email.', [
            'outbound_email_id' => $this->email->id,
            'exception' => $exception::class,
        ]);
    }
}
