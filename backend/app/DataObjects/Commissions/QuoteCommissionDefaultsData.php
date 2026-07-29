<?php

declare(strict_types=1);

namespace App\DataObjects\Commissions;

use DateTimeImmutable;

final readonly class QuoteCommissionDefaultsData
{
    public function __construct(
        public ?int $quoteId,
        public int $productId,
        public string $lineNetAmount,
        public ?int $commercialId,
        public ?int $reporterId,
        public ?int $supervisorId,
        public DateTimeImmutable $referenceDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromValidated(array $data): self
    {
        return new self(
            quoteId: isset($data['quote_id']) ? (int) $data['quote_id'] : null,
            productId: (int) $data['product_id'],
            lineNetAmount: (string) $data['line_net_amount'],
            commercialId: isset($data['commercial_id']) ? (int) $data['commercial_id'] : null,
            reporterId: isset($data['reporter_id']) ? (int) $data['reporter_id'] : null,
            supervisorId: isset($data['supervisor_id']) ? (int) $data['supervisor_id'] : null,
            referenceDate: new DateTimeImmutable($data['reference_date'] ?? 'now'),
        );
    }
}
