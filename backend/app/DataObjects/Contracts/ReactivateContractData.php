<?php

declare(strict_types=1);

namespace App\DataObjects\Contracts;

/**
 * Validated payload for POST /api/contracts/{contract}/reactivate (spec
 * 0072, BR-2, extended by the user directive of 2026-08-31). The action now
 * serves TWO paths and the payload tells them apart:
 *
 * - suspended contract → no field at all: the pre-suspension status is
 *   restored server-side (D-3), nothing is submitted.
 * - disdetto contract  → `contract_status_id` is mandatory (the user picks
 *   the destination status in the dialog), since no column ever recorded the
 *   status the contract sat on before the disdetta.
 */
final readonly class ReactivateContractData
{
    public function __construct(
        public ?int $contractStatusId = null,
        public bool $contractStatusIdSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            contractStatusId: array_key_exists('contract_status_id', $data) ? (int) $data['contract_status_id'] : null,
            contractStatusIdSubmitted: array_key_exists('contract_status_id', $data),
        );
    }
}
