<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\DataObjects\CostTypes\CreateCostTypeData;
use App\DataObjects\CostTypes\UpdateCostTypeData;
use App\Enums\ChartAccountKind;
use App\Exports\ChartOfAccounts\ChartOfAccountsLayout as Layout;
use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use App\Services\ChartOfAccounts\ChartAccountService;
use App\Services\ChartOfAccounts\RevenueTypeService;
use App\Services\CostTypeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Executes an error-free plan in one transaction (spec 0349, D-7): groups, then
 * accounts, then sub-accounts, always through the existing services so audit
 * and rules are those of the UI (D-12).
 */
final class ChartOfAccountsApplier
{
    /** Badge color of a cost type created without one (D-10): first of BadgeTokens::colors(). */
    public const string DEFAULT_COLOR = 'slate';

    private const array LEVEL_ORDER = [Layout::LEVEL_GROUP, Layout::LEVEL_ACCOUNT, Layout::LEVEL_SUB_ACCOUNT];

    /** @var array<string, int> chart node ids by lower-case code, filled as nodes are written */
    private array $nodeIds = [];

    public function __construct(
        private readonly ChartAccountService $accounts,
        private readonly RevenueTypeService $revenueTypes,
        private readonly CostTypeService $costTypes,
    ) {}

    public function apply(ChartImportPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $this->nodeIds = [];

            foreach (self::LEVEL_ORDER as $level) {
                foreach ($plan->entries as $entry) {
                    if ($entry->row->level === $level) {
                        $this->applyEntry($entry);
                    }
                }
            }
        });
    }

    private function applyEntry(PlannedEntry $entry): void
    {
        match ($entry->row->level) {
            Layout::LEVEL_GROUP => $this->applyGroup($entry),
            Layout::LEVEL_ACCOUNT => $this->applyAccount($entry),
            default => $entry->kind === ChartAccountKind::Cost ? $this->applyCostType($entry) : $this->applyRevenueType($entry),
        };
    }

    private function applyGroup(PlannedEntry $entry): void
    {
        $row = $entry->row;
        $attributes = ['kind' => $row->kind?->value, ...$this->common($entry)];

        $group = $entry->existing instanceof ChartAccount
            ? $this->accounts->update($entry->existing, $attributes)
            : $this->accounts->create(['level' => Layout::LEVEL_GROUP, 'code' => $row->code, ...$attributes]);

        $this->nodeIds[Str::lower($row->code)] = $group->id;
    }

    private function applyAccount(PlannedEntry $entry): void
    {
        $row = $entry->row;
        $attributes = ['parent_id' => $this->parentId($row), ...$this->common($entry)];

        $account = $entry->existing instanceof ChartAccount
            ? $this->accounts->update($entry->existing, $attributes)
            : $this->accounts->create(['level' => Layout::LEVEL_ACCOUNT, 'code' => $row->code, ...$attributes]);

        $this->nodeIds[Str::lower($row->code)] = $account->id;
    }

    private function applyRevenueType(PlannedEntry $entry): void
    {
        $attributes = ['chart_account_id' => $this->parentId($entry->row), ...$this->common($entry)];

        if ($entry->existing instanceof RevenueType) {
            $this->revenueTypes->update($entry->existing, $attributes);

            return;
        }

        $this->revenueTypes->create(['code' => $entry->row->code, ...$attributes]);
    }

    private function applyCostType(PlannedEntry $entry): void
    {
        $row = $entry->row;
        $attributes = [
            'name' => $row->name,
            'description' => $row->description,
            'nature' => $row->nature?->value,
            'is_active' => $row->isActive,
            'chart_account_id' => $this->parentId($row),
            'code' => $row->code,
        ];

        if ($entry->existing instanceof CostType) {
            $this->costTypes->update($entry->existing, UpdateCostTypeData::fromValidated($row->color === null ? $attributes : [...$attributes, 'color' => $row->color]));

            return;
        }

        $this->costTypes->create(CreateCostTypeData::fromValidated([...$attributes, 'color' => $row->color ?? self::DEFAULT_COLOR]));
    }

    /**
     * @return array{name: string, description: ?string, is_active: bool}
     */
    private function common(PlannedEntry $entry): array
    {
        return ['name' => $entry->row->name, 'description' => $entry->row->description, 'is_active' => $entry->row->isActive];
    }

    private function parentId(ParsedRow $row): int
    {
        $code = (string) $row->parentCode;

        return $this->nodeIds[Str::lower($code)] ?? (int) ChartAccount::query()->where('code', $code)->value('id');
    }
}
