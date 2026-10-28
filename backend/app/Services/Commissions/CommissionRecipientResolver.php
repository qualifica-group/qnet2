<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\CommissionRecipient;
use App\Enums\CommissionRecipientRole;

/**
 * Single source of truth for WHO may receive a commission on a quote line.
 *
 * The recipient is never free input: each role maps to exactly one upstream
 * selection made before the commission exists — the quote's
 * commercial/reporter/supervisor for the three people roles, the quote
 * line's own supplier (spec 0320, D-7) for the fourth. A role whose upstream selection is empty has NO
 * admissible recipient at all, so no commission may exist for it.
 *
 * Shared by the defaults initializer, the recipients endpoint that locks the
 * dialog client-side, and the write-side guard in ValidatesQuoteLines.
 */
final class CommissionRecipientResolver
{
    /**
     * @param  int|null  $supplierId  the quote line's `supplier_id` (null = no Supplier recipient)
     * @return array<string, CommissionRecipient|null> keyed by CommissionRecipientRole value
     */
    public function resolve(
        ?int $supplierId,
        ?int $commercialId,
        ?int $reporterId,
        ?int $supervisorId,
    ): array {
        return [
            CommissionRecipientRole::Commercial->value => $this->recipient(CommissionRecipientRole::Commercial, $commercialId),
            CommissionRecipientRole::Reporter->value => $this->recipient(CommissionRecipientRole::Reporter, $reporterId),
            CommissionRecipientRole::Supervisor->value => $this->recipient(CommissionRecipientRole::Supervisor, $supervisorId),
            CommissionRecipientRole::Supplier->value => $this->recipient(CommissionRecipientRole::Supplier, $supplierId),
        ];
    }

    private function recipient(CommissionRecipientRole $role, ?int $id): ?CommissionRecipient
    {
        return $id === null ? null : new CommissionRecipient($role->recipientType(), $id);
    }
}
