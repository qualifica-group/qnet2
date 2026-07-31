<?php

declare(strict_types=1);

namespace App\DataObjects\Contracts;

/**
 * Validated payload for POST /api/contracts/{contract}/schedule (spec 0072).
 * `expiry_date`/`contract_status_id` are mandatory (D-2: "Programmato" is a
 * plain, deletable row, never resolved by system_key — the destination
 * status ALWAYS comes from the client here); `renewal_date` is optional.
 */
final readonly class ScheduleContractData
{
    public function __construct(
        public string $expiryDate,
        public ?string $renewalDate,
        public int $contractStatusId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            expiryDate: (string) $data['expiry_date'],
            renewalDate: $data['renewal_date'] ?? null,
            contractStatusId: (int) $data['contract_status_id'],
        );
    }
}
