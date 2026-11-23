<?php

declare(strict_types=1);

namespace App\WorkOrders;

use Illuminate\Container\Attributes\Tag;

/**
 * The read-only work order attributes: the union of what every registered
 * WorkOrderAttributeLock owns. With no lock registered (the default) every
 * attribute stays editable.
 */
final class WorkOrderAttributeLocks
{
    /**
     * @param  iterable<WorkOrderAttributeLock>  $locks
     */
    public function __construct(
        #[Tag('work-orders.attribute-locks')] private readonly iterable $locks = [],
    ) {}

    /**
     * @param  list<int>  $categoryIds
     * @return list<string>
     */
    public function lockedCodes(array $categoryIds): array
    {
        return array_column($this->lockedAttributes($categoryIds), 'code');
    }

    /**
     * Each locked code with the source that owns it (the first registered
     * lock claiming a code wins).
     *
     * @param  list<int>  $categoryIds
     * @return list<array{code: string, source: string}>
     */
    public function lockedAttributes(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $sources = [];

        foreach ($this->locks as $lock) {
            foreach ($lock->lockedCodes($categoryIds) as $code) {
                $sources[$code] ??= $lock->source();
            }
        }

        return array_map(
            static fn (string $code, string $source): array => ['code' => $code, 'source' => $source],
            array_keys($sources),
            array_values($sources),
        );
    }

    /**
     * The locked codes a submission would change. Re-sending the stored
     * value (a whole-form save) is not a change; an empty string equals null.
     *
     * @param  list<string>  $lockedCodes
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $submitted
     * @return list<string>
     */
    public function changedCodes(array $lockedCodes, array $current, array $submitted): array
    {
        return array_values(array_filter(
            $lockedCodes,
            static fn (string $code): bool => array_key_exists($code, $submitted)
                && self::comparable($submitted[$code]) !== self::comparable($current[$code] ?? null),
        ));
    }

    private static function comparable(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : $value;
    }
}
