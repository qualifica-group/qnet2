<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\StatusSystemKey;
use App\Models\ContractStatus;
use App\Services\Statuses\SystemStatusGuard;

/**
 * Shared `contract_statuses` id lookups (spec 0072): the active `is_default`
 * row (fallback for BR-1's contract creation and BR-2's reactivation) and a
 * system row by its `system_key` (BR-1's 'suspended', BR-4's 'terminated'
 * default destination) — used by both ContractLifecycleManager and
 * ContractActionService so the two domain layers never resolve these ids
 * differently.
 */
class ContractStatusResolver
{
    public function __construct(private readonly SystemStatusGuard $systemStatusGuard) {}

    /**
     * The active `is_default` row, falling back to the mandatory system
     * 'new' row (D-2's HEAD) if, somehow, no active default exists.
     */
    public function defaultActiveId(): int
    {
        $id = ContractStatus::query()->where('is_default', true)->where('is_active', true)->value('id');

        return $id !== null ? (int) $id : $this->systemStatusGuard->resolveNewStatusId(ContractStatus::class);
    }

    public function systemId(StatusSystemKey $key): int
    {
        return (int) ContractStatus::query()->where('system_key', $key->value)->value('id');
    }
}
