<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use Illuminate\Database\Eloquent\Model;

/**
 * What the import will do for one row: create a new entry or update `existing`.
 * `kind` is the cost/revenue kind of the entry (own for a group, inherited for
 * accounts and sub-accounts).
 */
final readonly class PlannedEntry
{
    public function __construct(
        public ParsedRow $row,
        public ?Model $existing,
        public ?ChartAccountKind $kind,
    ) {}

    public function isCreate(): bool
    {
        return $this->existing === null;
    }
}
