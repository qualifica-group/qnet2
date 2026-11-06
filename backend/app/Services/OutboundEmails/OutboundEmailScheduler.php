<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Enums\OutboundEmailStatus;
use App\Models\OutboundEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Programs a draft as a recurring series and brings it back to a draft
 * (spec 0362, D-6/D-8). The programmed email is never sent itself: the
 * RecurringEmailDispatcher sends a COPY at every date. Like
 * OutboundEmailService, owner-level authorization is the controller's job and
 * the email is already resolved and visible to $actor.
 */
final class OutboundEmailScheduler
{
    public function __construct(
        private readonly OutboundEmailService $service,
        private readonly EmailOwnerRegistry $owners,
    ) {}

    /**
     * @param  array<string, mixed>  $rule  the validated `recurrence` object
     */
    public function schedule(Model $owner, OutboundEmail $email, User $actor, array $rule): OutboundEmail
    {
        // Step 1: only the author, only a draft, only while the owner is active
        $this->assertAuthor($email, $actor);

        if ($email->status !== OutboundEmailStatus::Draft) {
            abort(409, __('outbound_emails.not_draft'));
        }

        if (! $this->owners->for($owner)->allowsRecurrence($owner)) {
            abort(409, __('outbound_emails.recurrence_owner_not_active'));
        }

        // Step 2: the same checks as an immediate send (D-12)
        $this->service->assertSendable($email, $actor);

        // Step 3: store the rule and arm the series from its start date
        DB::transaction(function () use ($email, $rule): void {
            $recurrence = $email->recurrence ?? $email->recurrence()->make();
            $recurrence->fill($rule);
            $recurrence->forceFill([
                'next_send_on' => $rule['starts_on'],
                'last_sent_on' => null,
                'sent_count' => 0,
                'end_reason' => null,
            ])->save();

            $email->forceFill(['status' => OutboundEmailStatus::Scheduled])->save();
        });

        return $this->service->resolveVisibleOrFail($owner, $email->id, $actor);
    }

    /**
     * Scheduled or ended series -> draft, rule kept (the way to stop a series).
     */
    public function unschedule(Model $owner, OutboundEmail $email, User $actor): OutboundEmail
    {
        $this->assertAuthor($email, $actor);

        if (! in_array($email->status, [OutboundEmailStatus::Scheduled, OutboundEmailStatus::ScheduleEnded], true)) {
            abort(409, __('outbound_emails.not_scheduled'));
        }

        DB::transaction(function () use ($email): void {
            $email->recurrence?->forceFill(['next_send_on' => null])->save();
            $email->forceFill(['status' => OutboundEmailStatus::Draft])->save();
        });

        return $this->service->resolveVisibleOrFail($owner, $email->id, $actor);
    }

    private function assertAuthor(OutboundEmail $email, User $actor): void
    {
        if ((int) $email->sender_user_id !== $actor->id) {
            abort(403, __('outbound_emails.not_author'));
        }
    }
}
