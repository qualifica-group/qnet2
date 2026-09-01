<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\CommissionRecipient;
use App\Models\Referent;

/**
 * Spec 0090 D-5/D-7. A referent and the user declared to BE it
 * (`referents.user_id`, D-1) denote the SAME person for rule matching: a
 * rule intestata a either identity applies. This is a question distinct
 * from `CommissionRecipientResolver` (who may receive a commission on THIS
 * quote) — that resolver's semantics do not change (D-7); this service only
 * widens WHICH rules match the recipient it already resolved.
 *
 * Not injected into `Referent::user()` (owned by a parallel teammate): reads
 * the `user_id` column directly, both directions, so it works whether that
 * relation exists yet or not.
 */
final class CommissionRecipientIdentities
{
    /**
     * @return array<int, CommissionRecipient> the recipient itself, plus its
     *                                         linked counterpart when one is declared (D-11: with `user_id`
     *                                         nowhere set, this always degenerates to just the recipient).
     */
    public function forRecipient(CommissionRecipient $recipient): array
    {
        return match ($recipient->type) {
            'referent' => $this->withLinkedUser($recipient),
            'user' => $this->withLinkedReferent($recipient),
            // A registry (Supplier) has no counterpart identity (goal: "Il
            // Fornitore resta un'anagrafica").
            default => [$recipient],
        };
    }

    /** @return array<int, CommissionRecipient> */
    private function withLinkedUser(CommissionRecipient $recipient): array
    {
        $userId = Referent::query()->whereKey($recipient->id)->value('user_id');

        return $userId === null ? [$recipient] : [$recipient, new CommissionRecipient('user', (int) $userId)];
    }

    /** @return array<int, CommissionRecipient> */
    private function withLinkedReferent(CommissionRecipient $recipient): array
    {
        // Unique by D-1: at most one referent is linked to a given user.
        $referentId = Referent::query()->where('user_id', $recipient->id)->value('id');

        return $referentId === null ? [$recipient] : [$recipient, new CommissionRecipient('referent', (int) $referentId)];
    }
}
