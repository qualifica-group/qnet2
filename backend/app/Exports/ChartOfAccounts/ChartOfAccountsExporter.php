<?php

declare(strict_types=1);

namespace App\Exports\ChartOfAccounts;

use App\Enums\ExportFormat;
use App\Exports\ExportWriter;
use App\Exports\ExportWriterFactory;
use App\Models\User;
use App\Services\ChartOfAccounts\ChartOfAccountsTreeBuilder;

/**
 * Writes the whole chart as a file (spec 0349, D-3): one row per entry in tree
 * order (group, its accounts, each followed by its sub-accounts). Cost types
 * not yet placed in the chart are not part of it (D-4).
 */
final class ChartOfAccountsExporter
{
    public function __construct(
        private readonly ChartOfAccountsTreeBuilder $tree,
        private readonly ExportWriterFactory $writers,
    ) {}

    public function write(ExportFormat $format, string $path, User $actor): void
    {
        // Step 1: header row in the user's language
        $writer = $this->writers->make($format);
        $writer->open($path);
        $writer->writeHeaders(array_map(ChartOfAccountsLayout::header(...), ChartOfAccountsLayout::COLUMNS));

        // Step 2: one row per entry, in tree order
        foreach ($this->tree->build($actor)['groups'] as $group) {
            $this->writeBranch($writer, $group);
        }

        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function writeBranch(ExportWriter $writer, array $group): void
    {
        $writer->writeRow($this->row(ChartOfAccountsLayout::LEVEL_GROUP, $group, null, $group['kind']));

        foreach ($group['accounts'] as $account) {
            $writer->writeRow($this->row(ChartOfAccountsLayout::LEVEL_ACCOUNT, $account, $group['code']));

            foreach ($account['sub_accounts'] as $subAccount) {
                $writer->writeRow($this->row(ChartOfAccountsLayout::LEVEL_SUB_ACCOUNT, $subAccount, $account['code'], null, $subAccount['nature'], $subAccount['color']));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string|null>
     */
    private function row(string $level, array $node, ?string $parentCode, ?string $kind = null, ?string $nature = null, ?string $color = null): array
    {
        return [
            ChartOfAccountsLayout::value(ChartOfAccountsLayout::LEVELS, $level),
            $node['code'],
            $parentCode,
            $node['name'],
            $node['description'],
            $kind === null ? null : ChartOfAccountsLayout::value(ChartOfAccountsLayout::KINDS, $kind),
            $nature === null ? null : ChartOfAccountsLayout::value(ChartOfAccountsLayout::NATURES, $nature),
            $color,
            ChartOfAccountsLayout::booleanLabel((bool) $node['is_active']),
        ];
    }
}
