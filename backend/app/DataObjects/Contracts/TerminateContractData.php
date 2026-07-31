<?php

declare(strict_types=1);

namespace App\DataObjects\Contracts;

/**
 * Validated payload for POST /api/contracts/{contract}/terminate (spec
 * 0072, BR-4). `terminated_at`/`termination_reason` are mandatory;
 * `contract_status_id` is optional — when omitted, the destination defaults
 * to the system 'terminated' row (D-2).
 */
final readonly class TerminateContractData
{
    public function __construct(
        public string $terminatedAt,
        public string $terminationReason,
        public ?int $contractStatusId = null,
        public bool $contractStatusIdSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            terminatedAt: (string) $data['terminated_at'],
            terminationReason: (string) $data['termination_reason'],
            contractStatusId: array_key_exists('contract_status_id', $data) ? (int) $data['contract_status_id'] : null,
            contractStatusIdSubmitted: array_key_exists('contract_status_id', $data),
        );
    }
}
