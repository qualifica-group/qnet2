<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use App\Exports\ChartOfAccounts\ChartOfAccountsLayout as Layout;
use App\Http\Requests\CostTypes\StoreCostTypeRequest;
use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Validates the whole file against itself and the current chart and decides,
 * row by row, what would be created or updated (spec 0349, D-5, D-7, D-9).
 * Nothing is written here: the plan is what the preview shows and what the
 * applier executes, so both always agree.
 */
final class ChartOfAccountsPlanner
{
    private ExistingChart $chart;

    private ImportErrors $errors;

    private User $actor;

    /** @var array<string, ParsedRow> first row of each (lower-case) code */
    private array $fileIndex = [];

    /** @var array<int, true> cost types already targeted by a row */
    private array $claimedCostTypes = [];

    /** @var array<string, true> lower-case names of the cost sub-accounts in the file */
    private array $costNamesInFile = [];

    public function __construct(private readonly ChartRowParser $parser) {}

    /**
     * @param  list<ImportRow>  $rows
     */
    public function plan(array $rows, User $actor): ChartImportPlan
    {
        // Step 1: syntax of every row, and a fresh snapshot of the chart
        $this->errors = new ImportErrors;
        $this->chart = new ExistingChart;
        $this->actor = $actor;
        $this->fileIndex = $this->claimedCostTypes = $this->costNamesInFile = [];
        $parsed = array_map(fn (ImportRow $row): ParsedRow => $this->parser->parse($row, $this->errors), $rows);

        // Step 2: index the file by code so parents can be found in it
        foreach ($parsed as $row) {
            if ($row->level !== null && $row->code !== '') {
                $this->fileIndex[Str::lower($row->code)] ??= $row;
            }
        }

        // Step 3: cross-row rules, producing one entry per valid row
        $entries = [];
        foreach ($parsed as $row) {
            $entry = $row->isValid ? $this->planRow($row) : null;

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return new ChartImportPlan($entries, $this->errors, count($rows));
    }

    private function planRow(ParsedRow $row): ?PlannedEntry
    {
        if ($this->fileIndex[Str::lower($row->code)] !== $row) {
            $this->errors->add($row->number, 'code', 'duplicate_in_file');

            return null;
        }

        return match ($row->level) {
            Layout::LEVEL_GROUP => $this->planGroup($row),
            Layout::LEVEL_ACCOUNT => $this->planAccount($row),
            default => $this->planSubAccount($row),
        };
    }

    private function planGroup(ParsedRow $row): ?PlannedEntry
    {
        $existing = $this->chart->find($row->code);

        if ($existing !== null && ! $this->isChartNode($existing, ChartAccountLevel::Group)) {
            $this->errors->add($row->number, 'code', 'code_other_level');

            return null;
        }

        if ($existing instanceof ChartAccount && $existing->kind !== $row->kind && $this->chart->groupHasAccounts($existing)) {
            $this->errors->add($row->number, 'kind', 'kind_locked');
        }

        return new PlannedEntry($row, $existing, $row->kind);
    }

    private function planAccount(ParsedRow $row): ?PlannedEntry
    {
        $existing = $this->chart->find($row->code);

        if ($existing !== null && ! $this->isChartNode($existing, ChartAccountLevel::Account)) {
            $this->errors->add($row->number, 'code', 'code_other_level');

            return null;
        }

        $parent = $this->parentNode($row, Layout::LEVEL_GROUP);
        if ($parent === null) {
            return null;
        }

        if ($existing instanceof ChartAccount && $parent['kind'] !== null && $this->chart->effectiveKind($existing) !== $parent['kind']) {
            $this->errors->add($row->number, 'parent_code', 'parent_kind_mismatch');
        }

        return new PlannedEntry($row, $existing, $parent['kind']);
    }

    private function planSubAccount(ParsedRow $row): ?PlannedEntry
    {
        $existing = $this->chart->find($row->code);

        if ($this->parentNode($row, Layout::LEVEL_ACCOUNT) === null) {
            return null;
        }

        $kind = $this->kindOf((string) $row->parentCode);
        $matchesKind = match ($kind) {
            ChartAccountKind::Cost => $existing instanceof CostType,
            ChartAccountKind::Revenue => $existing instanceof RevenueType,
            default => $existing instanceof CostType || $existing instanceof RevenueType,
        };

        if ($existing !== null && ! $matchesKind) {
            $this->errors->add($row->number, 'code', 'code_other_level');

            return null;
        }

        return match ($kind) {
            ChartAccountKind::Cost => $this->planCostType($row, $existing instanceof CostType ? $existing : null),
            ChartAccountKind::Revenue => new PlannedEntry($row, $existing, $kind),
            default => null,
        };
    }

    /**
     * A cost sub-account needs a nature, a unique name (a new code with the name
     * of an existing cost type adopts that type, D-5) and the cost-types permission.
     */
    private function planCostType(ParsedRow $row, ?CostType $existing): ?PlannedEntry
    {
        if ($row->nature === null) {
            $this->errors->add($row->number, 'nature', 'required');
        }

        if (mb_strlen($row->name) > StoreCostTypeRequest::NAME_MAX) {
            $this->errors->add($row->number, 'name', 'too_long', ['max' => StoreCostTypeRequest::NAME_MAX]);

            return null;
        }

        $sameName = $this->chart->costTypeByName($row->name);
        $nameKey = Str::lower($row->name);
        $takenByOther = $sameName !== null && $sameName->id !== $existing?->id
            && ($existing !== null || isset($this->claimedCostTypes[$sameName->id]));

        if (isset($this->costNamesInFile[$nameKey]) || $takenByOther) {
            $this->errors->add($row->number, 'name', 'name_taken');

            return null;
        }
        $this->costNamesInFile[$nameKey] = true;

        $existing ??= $sameName;
        if ($existing !== null) {
            if (isset($this->claimedCostTypes[$existing->id])) {
                $this->errors->add($row->number, 'code', 'duplicate_in_file');

                return null;
            }
            $this->claimedCostTypes[$existing->id] = true;
        }

        $ability = $existing === null ? 'create' : 'update';
        if (! $this->actor->can("cost-types.{$ability}")) {
            $this->errors->add($row->number, null, "no_permission_{$ability}_cost_type");

            return null;
        }

        return new PlannedEntry($row, $existing, ChartAccountKind::Cost);
    }

    /**
     * The row's parent, which must exist (in the file or the chart) at the given level.
     *
     * @return array{level: ?string, kind: ?ChartAccountKind, parent_code: ?string}|null
     */
    private function parentNode(ParsedRow $row, string $expectedLevel): ?array
    {
        $node = $this->node((string) $row->parentCode);

        if ($node === null) {
            $this->errors->add($row->number, 'parent_code', 'parent_not_found');

            return null;
        }

        if ($node['level'] !== $expectedLevel) {
            $this->errors->add($row->number, 'parent_code', 'parent_wrong_level');

            return null;
        }

        return $node;
    }

    /**
     * A code as the file will leave it: the file row wins over the current chart.
     *
     * @return array{level: ?string, kind: ?ChartAccountKind, parent_code: ?string}|null
     */
    private function node(string $code): ?array
    {
        $row = $this->fileIndex[Str::lower($code)] ?? null;
        if ($row !== null) {
            return ['level' => $row->level, 'kind' => $row->kind, 'parent_code' => $row->parentCode];
        }

        $existing = $this->chart->find($code);
        if ($existing instanceof ChartAccount) {
            return ['level' => $existing->level->value, 'kind' => $existing->kind, 'parent_code' => $this->chart->parentCode($existing)];
        }

        return $existing === null ? null : ['level' => Layout::LEVEL_SUB_ACCOUNT, 'kind' => null, 'parent_code' => null];
    }

    private function kindOf(string $accountCode): ?ChartAccountKind
    {
        $account = $this->node($accountCode);
        $group = $account === null || $account['parent_code'] === null ? null : $this->node($account['parent_code']);

        return $group !== null && $group['level'] === Layout::LEVEL_GROUP ? $group['kind'] : null;
    }

    private function isChartNode(mixed $entry, ChartAccountLevel $level): bool
    {
        return $entry instanceof ChartAccount && $entry->level === $level;
    }
}
