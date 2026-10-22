<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\CostType;
use RuntimeException;

/**
 * Cost type by name (trim, case-insensitive) for the legacy purchase order and
 * supplier invoice lines (specs 0296, 0298): the legacy sends the ODA type,
 * whose names are the qnet-2 cost types. Cached for the whole run.
 */
final class LegacyCostTypeLookup
{
    /** @var array<string, int>|null */
    private ?array $ids = null;

    /**
     * @throws RuntimeException when the cost type does not exist
     */
    public function idFor(mixed $name, string $label): int
    {
        $this->ids ??= CostType::query()->get(['id', 'name'])
            ->mapWithKeys(fn (CostType $costType): array => [mb_strtolower(trim($costType->name)) => $costType->id])
            ->all();
        $name = trim((string) ($name ?? ''));

        return $this->ids[mb_strtolower($name)]
            ?? throw new RuntimeException("{$label}: cost type '{$name}' not found; the record is not imported.");
    }
}
