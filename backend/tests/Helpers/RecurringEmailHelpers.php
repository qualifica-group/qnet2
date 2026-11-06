<?php

declare(strict_types=1);

use App\Enums\OutboundEmailStatus;
use App\Models\OutboundEmail;
use App\Models\OutboundEmailRecurrence;
use App\Models\User;
use App\Models\WorkOrder;

if (! function_exists('recurringRuleInput')) {
    /**
     * A valid weekly (Monday) `recurrence` request object starting next
     * Monday and ending three weeks later (spec 0362).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function recurringRuleInput(array $overrides = []): array
    {
        $start = today()->next('monday');

        return [
            'frequency' => 'weekly',
            'interval' => 1,
            'weekdays' => [1],
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->addWeeks(3)->toDateString(),
            ...$overrides,
        ];
    }
}

if (! function_exists('recurringDraft')) {
    /**
     * A sendable draft of $author on $workOrder.
     *
     * @param  array<string, mixed>  $attributes
     */
    function recurringDraft(WorkOrder $workOrder, User $author, array $attributes = []): OutboundEmail
    {
        return OutboundEmail::factory()->forEmailable($workOrder)->create([
            'sender_user_id' => $author->id,
            'to_recipients' => ['dest@example.com'],
            'cc_recipients' => ['cc@example.com'],
            'subject' => 'Promemoria',
            'body' => '<p>Corpo</p>',
            ...$attributes,
        ]);
    }
}

if (! function_exists('programmedEmail')) {
    /**
     * A `scheduled` email of $author with its series row, due on `next_send_on`.
     *
     * @param  array<string, mixed>  $rule  overrides of the OutboundEmailRecurrence factory
     * @param  array<string, mixed>  $attributes  overrides of the email
     */
    function programmedEmail(WorkOrder $workOrder, User $author, array $rule = [], array $attributes = []): OutboundEmail
    {
        $email = recurringDraft($workOrder, $author, ['status' => OutboundEmailStatus::Scheduled, ...$attributes]);
        OutboundEmailRecurrence::factory()->create(['outbound_email_id' => $email->id, ...$rule]);

        return $email->fresh();
    }
}
