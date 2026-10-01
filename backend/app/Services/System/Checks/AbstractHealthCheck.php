<?php

declare(strict_types=1);

namespace App\Services\System\Checks;

use App\Enums\HealthStatusEnum;

/**
 * Shared helpers for HealthCheck implementations: building a single detail
 * entry and deriving a check's own status from the worst of its details
 * (spec 0187 contract rule). Kept as a base class because all 4 checkers
 * apply this exact rule — real duplication, not speculative abstraction.
 */
abstract class AbstractHealthCheck implements HealthCheck
{
    /**
     * @param  array<int, array{key:string, status:HealthStatusEnum, value:?string, message:?string}>  $details
     */
    protected function statusFromDetails(array $details): HealthStatusEnum
    {
        return HealthStatusEnum::worst(...array_map(
            static fn (array $detail): HealthStatusEnum => $detail['status'],
            $details,
        ));
    }

    /**
     * @return array{key:string, status:HealthStatusEnum, value:?string, message:?string}
     */
    protected function detail(string $key, HealthStatusEnum $status, ?string $value = null, ?string $message = null): array
    {
        return ['key' => $key, 'status' => $status, 'value' => $value, 'message' => $message];
    }
}
