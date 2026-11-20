<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Notifications\QuoteStatusNotification;
use App\Services\Notifications\AssignmentNotifier;
use App\Support\Notifications\NotifyRecipients;
use App\Support\Notifications\RecordDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the email a working status is configured with (spec 0419) when a
 * user moves an offer into it. Called by QuoteWorkflowStatusWriter::apply(),
 * the one choke point of every user status change, once every check passed.
 * Silent under AssignmentNotifier::muted() (imports, migrations, D-4); the
 * notification leaves only once the write is durable, so a refused or
 * rolled-back change never sends anything.
 */
final class QuoteStatusNotifier
{
    public function entered(Quote $quote, QuoteWorkflowStatus $status, User $actor): void
    {
        // Step 1: nothing configured, or a system write
        $notify = $status->notify;

        if (! is_array($notify) || AssignmentNotifier::isMuted()) {
            return;
        }

        // Step 2: after the commit the offer is saved with its new status
        DB::afterCommit(fn () => $this->dispatch($quote, $status->name, $notify, $actor));
    }

    /**
     * @param  array<string, mixed>  $notify
     */
    private function dispatch(Quote $quote, string $statusName, array $notify, User $actor): void
    {
        $users = NotifyRecipients::users($quote, $notify, $actor);
        $emails = NotifyRecipients::freeAddresses($notify, $users, $actor);

        if ($users->isEmpty() && $emails === []) {
            return;
        }

        $label = trim(RecordDetails::labelFor($quote));
        $notification = new QuoteStatusNotification(
            subject: (string) $notify['subject'],
            message: (string) $notify['message'],
            statusName: $statusName,
            recordLabel: $label === '' ? '#'.$quote->getKey() : $label,
            quoteId: (int) $quote->getKey(),
            actorName: $actor->name,
            recordDetails: RecordDetails::for($quote),
        );

        Notification::send($users, $notification);

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
