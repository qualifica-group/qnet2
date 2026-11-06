<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\DataObjects\Tasks\TaskRecurrenceData;
use App\Enums\OutboundEmailRecurrenceEndReason;
use App\Enums\OutboundEmailStatus;
use App\Enums\TaskRecurrenceEnd;
use App\Jobs\SendOutboundEmailJob;
use App\Models\OutboundEmail;
use App\Models\OutboundEmailRecurrence;
use App\Models\User;
use App\Services\Tasks\TaskRecurrenceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends the due occurrences of the recurring email series (spec 0362,
 * D-6/D-9/D-10): for every `scheduled` series whose `next_send_on` has come,
 * ends it when the owner is no longer active or the author can no longer send,
 * otherwise creates ONE queued copy of the programmed email and moves the
 * series to its next date. The programmed email itself is never sent.
 * Dates come only from TaskRecurrenceCalculator, anchored on `starts_on`.
 */
final class RecurringEmailDispatcher
{
    public function __construct(
        private readonly TaskRecurrenceCalculator $calculator,
        private readonly OutboundEmailAttachmentService $attachments,
        private readonly EmailOwnerRegistry $owners,
        private readonly OutboundEmailSenderAddresses $senderAddresses,
    ) {}

    /**
     * Returns how many copies were sent (or, on a dry run, would be sent).
     */
    public function handle(CarbonImmutable $now, bool $dryRun = false): int
    {
        // Step 1: nothing goes out before the configured time of day
        if ($now->lt($now->setTimeFromTimeString((string) config('outbound_emails.recurrence.send_at')))) {
            return 0;
        }

        $today = $now->startOfDay();
        $sent = 0;

        // Step 2: one series at a time, a broken one never blocks the others
        foreach ($this->dueSeries($today) as $recurrence) {
            try {
                $sent += $this->processSeries($recurrence, $today, $dryRun) ? 1 : 0;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, OutboundEmailRecurrence>
     */
    private function dueSeries(CarbonImmutable $today): Collection
    {
        return OutboundEmailRecurrence::query()
            ->whereHas('email', fn ($email) => $email->where('status', OutboundEmailStatus::Scheduled->value))
            ->whereDate('next_send_on', '<=', $today->toDateString())
            ->with([
                'email.emailable',
                'email.sender',
                'email.attachments' => fn ($query) => $query->where('collection', OutboundEmail::ATTACHMENT_COLLECTION),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * True when a copy was (or, dry run, would be) sent.
     */
    private function processSeries(OutboundEmailRecurrence $recurrence, CarbonImmutable $today, bool $dryRun): bool
    {
        $source = $recurrence->email;
        $author = $source->sender;

        // Step 3: end the series instead of sending when it cannot go on
        $endReason = $this->endReason($recurrence, $source, $author, $today);

        if ($endReason !== null) {
            if (! $dryRun) {
                $this->endSeries($recurrence, $source, $endReason);
            }

            return false;
        }

        if ($dryRun) {
            return true;
        }

        // Step 4: copy + attachments + series advance in ONE transaction, the
        // job only after it (a rolled-back copy must never be delivered)
        $copy = DB::transaction(fn (): ?OutboundEmail => $this->sendOccurrence($recurrence, $source, $author, $today));

        if ($copy === null) {
            return false;
        }

        SendOutboundEmailJob::dispatch($copy);

        return true;
    }

    private function endReason(OutboundEmailRecurrence $recurrence, OutboundEmail $source, User $author, CarbonImmutable $today): ?OutboundEmailRecurrenceEndReason
    {
        $owner = $source->emailable;

        return match (true) {
            $owner === null || ! $this->owners->for($owner)->allowsRecurrence($owner) => OutboundEmailRecurrenceEndReason::OwnerClosed,
            ! $author->is_active || trim((string) $author->email) === '' => OutboundEmailRecurrenceEndReason::SenderUnavailable,
            $today->gt($recurrence->ends_on->toImmutable()) => OutboundEmailRecurrenceEndReason::EndsOnReached,
            default => null,
        };
    }

    private function endSeries(OutboundEmailRecurrence $recurrence, OutboundEmail $source, OutboundEmailRecurrenceEndReason $reason): void
    {
        DB::transaction(function () use ($recurrence, $source, $reason): void {
            $recurrence->forceFill(['next_send_on' => null, 'end_reason' => $reason])->save();
            $source->forceFill(['status' => OutboundEmailStatus::ScheduleEnded])->save();
        });
    }

    /**
     * Null when today's occurrence already exists (the UNIQUE pair's
     * idempotency): the series just moves on, without counting a send.
     */
    private function sendOccurrence(OutboundEmailRecurrence $recurrence, OutboundEmail $source, User $author, CarbonImmutable $today): ?OutboundEmail
    {
        $alreadySent = OutboundEmail::query()
            ->where('outbound_email_recurrence_id', $recurrence->id)
            ->whereDate('occurrence_on', $today->toDateString())
            ->exists();

        $copy = $alreadySent ? null : $this->createCopy($recurrence, $source, $author, $today);

        $this->advance($recurrence, $source, $today, counted: $copy !== null);

        return $copy;
    }

    private function createCopy(OutboundEmailRecurrence $recurrence, OutboundEmail $source, User $author, CarbonImmutable $today): OutboundEmail
    {
        $copy = (new OutboundEmail)->forceFill([
            'emailable_type' => $source->emailable_type,
            'emailable_id' => $source->emailable_id,
            'email_template_id' => $source->email_template_id,
            'purpose' => $source->purpose,
            'to_recipients' => $source->to_recipients,
            'cc_recipients' => $source->cc_recipients,
            'bcc_recipients' => $source->bcc_recipients,
            'subject' => $source->subject,
            'body' => $source->body,
            'sender_user_id' => $author->id,
            'status' => OutboundEmailStatus::Draft,
            'outbound_email_recurrence_id' => $recurrence->id,
            'occurrence_on' => $today->toDateString(),
        ]);
        $copy->save();

        $this->attachments->copyAll($source->attachments, $copy, $author);

        $copy->markQueued($this->fromAddress($source, $author));

        return $copy;
    }

    /**
     * The mailbox the author chose on the programmed email, while it is still
     * one of theirs, otherwise their login email.
     */
    private function fromAddress(OutboundEmail $source, User $author): string
    {
        $chosen = (string) $source->from_address;

        return $chosen !== '' && $this->senderAddresses->allows($author, $chosen) ? $chosen : (string) $author->email;
    }

    private function advance(OutboundEmailRecurrence $recurrence, OutboundEmail $source, CarbonImmutable $today, bool $counted): void
    {
        $next = $this->nextDateAfter($recurrence, $today);

        $recurrence->forceFill([
            'sent_count' => $recurrence->sent_count + ($counted ? 1 : 0),
            'last_sent_on' => $counted ? $today->toDateString() : $recurrence->last_sent_on,
            'next_send_on' => $next?->toDateString(),
            'end_reason' => $next === null ? OutboundEmailRecurrenceEndReason::EndsOnReached : null,
        ])->save();

        if ($next === null) {
            $source->forceFill(['status' => OutboundEmailStatus::ScheduleEnded])->save();
        }
    }

    /**
     * The first date of the rule strictly after $day and not after `ends_on`.
     * Always recomputed from the `starts_on` anchor: resuming from a date
     * shifted by `workdays_only` would break the interval alignment.
     */
    private function nextDateAfter(OutboundEmailRecurrence $recurrence, CarbonImmutable $day): ?CarbonImmutable
    {
        $dates = $this->calculator->nextDates($this->ruleData($recurrence), $recurrence->starts_on->toImmutable(), PHP_INT_MAX);

        foreach ($dates as $date) {
            if ($date->gt($day)) {
                return $date;
            }
        }

        return null;
    }

    private function ruleData(OutboundEmailRecurrence $recurrence): TaskRecurrenceData
    {
        return new TaskRecurrenceData(
            frequency: $recurrence->frequency,
            interval: $recurrence->interval,
            weekdays: $recurrence->weekdays,
            monthDay: $recurrence->month_day,
            monthMode: $recurrence->month_mode,
            ordinal: $recurrence->ordinal,
            ordinalWeekday: $recurrence->ordinal_weekday,
            yearMonth: $recurrence->year_month,
            workdaysOnly: $recurrence->workdays_only,
            ends: TaskRecurrenceEnd::OnDate,
            endsOn: $recurrence->ends_on->toDateString(),
            occurrenceCount: null,
        );
    }
}
