<?php

declare(strict_types=1);

namespace App\DataObjects\Contracts;

/**
 * Validated payload for POST /api/contracts/{contract}/change-status (user
 * directive 2026-08-31 rev.2). One mandatory field: the destination status,
 * restricted to the `open`/`pending` groups — "Modifica stato" moves a
 * contract WITHIN its working phase, never across a closure (validating and
 * disdicendo are the two actions that close it).
 */
final readonly class ChangeContractStatusData
{
    public function __construct(public int $contractStatusId) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(contractStatusId: (int) $data['contract_status_id']);
    }
}
