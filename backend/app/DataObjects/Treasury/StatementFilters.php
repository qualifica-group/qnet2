<?php

declare(strict_types=1);

namespace App\DataObjects\Treasury;

use Carbon\CarbonImmutable;

/** Validated parameters of an account statement (spec 0360). */
final readonly class StatementFilters
{
    /**
     * @param  array<int, string>  $types
     */
    public function __construct(
        public CarbonImmutable $dateFrom,
        public CarbonImmutable $dateTo,
        public array $types = [],
        public ?int $registryId = null,
        public ?string $search = null,
        public ?bool $reconciled = null,
    ) {}

    public function isFiltered(): bool
    {
        return $this->types !== [] || $this->registryId !== null || $this->search !== null || $this->reconciled !== null;
    }
}
