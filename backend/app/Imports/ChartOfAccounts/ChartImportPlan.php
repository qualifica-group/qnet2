<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Exports\ChartOfAccounts\ChartOfAccountsLayout;

/**
 * The outcome of validating a file: the entries to write (meaningful only when
 * there are no errors) and the row errors.
 */
final readonly class ChartImportPlan
{
    /**
     * @param  list<PlannedEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public ImportErrors $errors,
        public int $rows,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors->count() > 0;
    }

    /**
     * @return array{rows: int, create: array<string, int>, update: array<string, int>}
     */
    public function summary(): array
    {
        $empty = ['groups' => 0, 'accounts' => 0, 'cost_sub_accounts' => 0, 'revenue_sub_accounts' => 0];
        $summary = ['rows' => $this->rows, 'create' => $empty, 'update' => $empty];

        foreach ($this->entries as $entry) {
            $summary[$entry->isCreate() ? 'create' : 'update'][$this->bucket($entry)]++;
        }

        return $summary;
    }

    private function bucket(PlannedEntry $entry): string
    {
        return match ($entry->row->level) {
            ChartOfAccountsLayout::LEVEL_GROUP => 'groups',
            ChartOfAccountsLayout::LEVEL_ACCOUNT => 'accounts',
            default => $entry->kind === ChartAccountKind::Cost ? 'cost_sub_accounts' : 'revenue_sub_accounts',
        };
    }
}
