<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use Illuminate\Support\Str;

/**
 * Read-only snapshot of the current chart for the planner: every entry by
 * (case-insensitive) code, cost types by name, and the tree facts the rules
 * need. Three queries, no lazy loading.
 */
final class ExistingChart
{
    /** @var array<string, ChartAccount|CostType|RevenueType> */
    private array $byCode = [];

    /** @var array<string, CostType> */
    private array $costTypesByName = [];

    /** @var array<int, ChartAccount> */
    private array $nodesById = [];

    /** @var array<int, true> ids of the groups that have at least one account */
    private array $groupsWithAccounts = [];

    public function __construct()
    {
        foreach (ChartAccount::query()->get() as $node) {
            $this->nodesById[$node->id] = $node;
            $this->byCode[Str::lower($node->code)] = $node;

            if ($node->level === ChartAccountLevel::Account) {
                $this->groupsWithAccounts[(int) $node->parent_id] = true;
            }
        }

        foreach (CostType::query()->get() as $costType) {
            $this->costTypesByName[Str::lower($costType->name)] = $costType;

            if ($costType->code !== null) {
                $this->byCode[Str::lower($costType->code)] = $costType;
            }
        }

        foreach (RevenueType::query()->get() as $revenueType) {
            $this->byCode[Str::lower($revenueType->code)] = $revenueType;
        }
    }

    public function find(string $code): ChartAccount|CostType|RevenueType|null
    {
        return $this->byCode[Str::lower($code)] ?? null;
    }

    public function costTypeByName(string $name): ?CostType
    {
        return $this->costTypesByName[Str::lower($name)] ?? null;
    }

    public function groupHasAccounts(ChartAccount $group): bool
    {
        return isset($this->groupsWithAccounts[$group->id]);
    }

    /**
     * The group's own kind, or the parent group's for an account.
     */
    public function effectiveKind(ChartAccount $node): ?ChartAccountKind
    {
        return $node->level === ChartAccountLevel::Group ? $node->kind : ($this->nodesById[(int) $node->parent_id] ?? null)?->kind;
    }

    /**
     * Code of the parent group of an existing account.
     */
    public function parentCode(ChartAccount $node): ?string
    {
        return $node->parent_id === null ? null : ($this->nodesById[$node->parent_id] ?? null)?->code;
    }
}
