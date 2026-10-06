<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * `GET .../emails/compose-context` (spec 0175, D-5/D-6/D-7/D-9; generalized
 * by owner in spec 0195, D-10): the owner-independent part (sender,
 * suggestions, size ceiling, optional `default_to`) plus the owner's own
 * extras, resolved ONCE server-side.
 */
final class OutboundEmailComposeContextBuilder
{
    public function __construct(private readonly EmailOwnerRegistry $owners) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Model $owner, User $actor): array
    {
        $emailOwner = $this->owners->for($owner);
        $defaultTo = $emailOwner->defaultTo($owner);

        return [
            'sender' => ['name' => (string) $actor->name, 'email' => (string) $actor->email],
            'recipient_suggestions' => $emailOwner->recipientSuggestions($owner, $actor),
            ...($defaultTo === [] ? [] : ['default_to' => $defaultTo]),
            ...$emailOwner->composeContextExtras($owner, $actor),
            'max_total_attachments_kb' => (int) config('outbound_emails.max_total_attachments_kb'),
        ];
    }
}
