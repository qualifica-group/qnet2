<?php

declare(strict_types=1);

namespace App\Services\Commissions\Accruals;

use App\Enums\CommissionRecipientRole;

/**
 * One ledger bucket (spec 0212, D-2): the movements of an invoice, quote line
 * and recipient role add up to `$share` of the commission at most. A bucket
 * without a current commission has share 0 and no commission id (expected 0).
 */
final readonly class AccrualBucket
{
    public function __construct(
        public int $invoiceId,
        public ?int $quoteLineId,
        public CommissionRecipientRole $role,
        public string $recipientType,
        public int $recipientId,
        public string $share,
        public ?int $commissionId,
    ) {}

    public static function key(int $invoiceId, ?int $quoteLineId, CommissionRecipientRole|string $role, string $recipientType, int $recipientId): string
    {
        $roleValue = $role instanceof CommissionRecipientRole ? $role->value : $role;

        return implode('|', [$invoiceId, $quoteLineId ?? 0, $roleValue, $recipientType, $recipientId]);
    }

    public function bucketKey(): string
    {
        return self::key($this->invoiceId, $this->quoteLineId, $this->role, $this->recipientType, $this->recipientId);
    }
}
