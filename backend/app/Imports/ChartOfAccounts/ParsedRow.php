<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Enums\CostTypeNature;

/**
 * A row after syntax validation: canonical values, no cross-row knowledge yet.
 * `level` is one of the `ChartOfAccountsLayout::LEVEL_*` values.
 */
final readonly class ParsedRow
{
    public function __construct(
        public int $number,
        public ?string $level,
        public string $code,
        public ?string $parentCode,
        public string $name,
        public ?string $description,
        public ?ChartAccountKind $kind,
        public ?CostTypeNature $nature,
        public ?string $color,
        public bool $isActive,
        public bool $isValid,
    ) {}
}
