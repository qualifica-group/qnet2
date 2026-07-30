<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\CommissionRecipient;
use App\Enums\CommissionRecipientRole;
use App\Models\Product;

/**
 * Single source of truth for WHO may receive a commission on a quote line.
 *
 * The recipient is never free input: each role maps to exactly one upstream
 * selection made before the commission exists — the quote's
 * commercial/reporter/supervisor for the three people roles, the product's
 * supplier for the fourth. A role whose upstream selection is empty has NO
 * admissible recipient at all, so no commission may exist for it.
 *
 * Shared by the defaults initializer, the recipients endpoint that locks the
 * dialog client-side, and the write-side guard in ValidatesQuoteLines.
 */
final class CommissionRecipientResolver
{
    /** @return array<string, CommissionRecipient|null> keyed by CommissionRecipientRole value */
    public function resolve(
        Product $product,
        ?int $commercialId,
        ?int $reporterId,
        ?int $supervisorId,
    ): array {
        return [
            CommissionRecipientRole::Commercial->value => $this->recipient('referent', $commercialId),
            CommissionRecipientRole::Reporter->value => $this->recipient('referent', $reporterId),
            CommissionRecipientRole::Supervisor->value => $this->recipient('user', $supervisorId),
            CommissionRecipientRole::Supplier->value => $this->recipient('registry', $product->supplier_id),
        ];
    }

    private function recipient(string $type, ?int $id): ?CommissionRecipient
    {
        return $id === null ? null : new CommissionRecipient($type, $id);
    }
}
