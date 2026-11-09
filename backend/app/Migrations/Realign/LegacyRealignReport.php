<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

/**
 * Everything one run found, in the shape of the Excel sheets (spec 0375). Held in
 * memory so a run stopped halfway still writes what it did.
 */
final class LegacyRealignReport
{
    public const string CHANGED_IN_LEGACY = 'changed_in_legacy';

    public const string NOT_ON_QNET2 = 'not_on_qnet2';

    public const string ALIGNED = 'aligned';

    public const string UNCHANGED = 'unchanged';

    public const string SKIPPED_TOUCHED = 'skipped_touched';

    public const string FAILED = 'failed';

    public const string DELETED_IN_LEGACY = 'deleted_in_legacy';

    public const array COUNTERS = [
        self::CHANGED_IN_LEGACY, self::NOT_ON_QNET2, self::ALIGNED, self::UNCHANGED,
        self::SKIPPED_TOUCHED, self::FAILED, self::DELETED_IN_LEGACY,
    ];

    /** @var array<string, array<string, int>> */
    public array $counters = [];

    /** @var list<list<string|int|float|bool|null>> */
    public array $changes = [];

    /** @var list<list<string|int|float|bool|null>> */
    public array $touched = [];

    /** @var list<list<string|int|float|bool|null>> */
    public array $deleted = [];

    /** @var list<list<string|int|float|bool|null>> */
    public array $messages = [];

    /** @var array<string, true> */
    public array $configurations = [];

    /** @var array<string, true> */
    public array $amountChanges = [];

    public function count(string $module, string $counter, int $by = 1): void
    {
        $this->counters[$module] ??= array_fill_keys(self::COUNTERS, 0);
        $this->counters[$module][$counter] += $by;
    }

    public function message(string $module, string $legacyKey, string $level, string $message): void
    {
        $this->messages[] = [$module, $legacyKey, $level, $message];
    }
}
