<?php

namespace App\Services\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use App\Models\TreasuryType;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the whole chart tree for GET /api/chart-of-accounts/tree (spec 0348,
 * D-12): four fixed queries (groups+accounts, cost, revenue and treasury types),
 * assembled and naturally sorted by code in PHP (`60.2` before `60.10`).
 */
class ChartOfAccountsTreeBuilder
{
    /**
     * @return array{groups: array<int, array<string, mixed>>, unplaced_cost_types: array<int, array<string, mixed>>, abilities: array<string, array<string, bool>>}
     */
    public function build(User $actor): array
    {
        // Step 1: the four queries
        $nodes = ChartAccount::query()->get();
        $costTypes = CostType::query()->get();
        $revenueTypes = RevenueType::query()->get();
        $treasuryTypes = TreasuryType::query()->get();

        // Step 2: sub-accounts grouped by parent account
        $subAccountsByAccount = $this->groupByAccount($costTypes, $revenueTypes, $treasuryTypes);

        // Step 3: assemble groups > accounts > sub-accounts
        $accountsByGroup = $nodes->where('level', ChartAccountLevel::Account)->groupBy('parent_id');
        $groups = $nodes->where('level', ChartAccountLevel::Group)
            ->map(fn (ChartAccount $group): array => $this->group($group, $accountsByGroup->get($group->id, collect()), $subAccountsByAccount))
            ->all();

        return [
            'groups' => $this->sortByCode($groups),
            'unplaced_cost_types' => $costTypes->whereNull('chart_account_id')
                ->sortBy('name')->map(fn (CostType $type): array => $this->costSubAccount($type))->values()->all(),
            'abilities' => $this->abilities($actor),
        ];
    }

    /**
     * @param  Collection<int, ChartAccount>  $accounts
     * @param  array<int, array<int, array<string, mixed>>>  $subAccountsByAccount
     * @return array<string, mixed>
     */
    private function group(ChartAccount $group, Collection $accounts, array $subAccountsByAccount): array
    {
        $children = $accounts->map(fn (ChartAccount $account): array => [
            ...$this->node($account),
            'sub_accounts' => $this->sortByCode($subAccountsByAccount[$account->id] ?? []),
        ])->all();

        return [
            ...$this->node($group),
            'kind' => $group->kind?->value,
            'accounts' => $this->sortByCode($children),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function node(ChartAccount $node): array
    {
        return [
            'id' => $node->id,
            'code' => $node->code,
            'name' => $node->name,
            'description' => $node->description,
            'is_active' => $node->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function costSubAccount(CostType $type): array
    {
        return [
            'id' => $type->id,
            'source' => 'cost_type',
            'code' => $type->code,
            'name' => $type->name,
            'description' => $type->description,
            'is_active' => $type->is_active,
            'nature' => $type->nature?->value,
            'color' => $type->color,
            'is_institution_commission_default' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenueSubAccount(RevenueType $type): array
    {
        return [
            'id' => $type->id,
            'source' => 'revenue_type',
            'code' => $type->code,
            'name' => $type->name,
            'description' => $type->description,
            'is_active' => $type->is_active,
            'nature' => null,
            'color' => null,
            'is_institution_commission_default' => $type->is_institution_commission_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function treasurySubAccount(TreasuryType $type): array
    {
        return [
            'id' => $type->id,
            'source' => 'treasury_type',
            'kind' => ChartAccountKind::Financial->value,
            'code' => $type->code,
            'name' => $type->name,
            'description' => $type->description,
            'is_active' => $type->is_active,
            'nature' => null,
            'color' => null,
            'is_institution_commission_default' => false,
        ];
    }

    /**
     * Placed cost types and all revenue and treasury types, keyed by their parent account id.
     *
     * @param  Collection<int, CostType>  $costTypes
     * @param  Collection<int, RevenueType>  $revenueTypes
     * @param  Collection<int, TreasuryType>  $treasuryTypes
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupByAccount(Collection $costTypes, Collection $revenueTypes, Collection $treasuryTypes): array
    {
        $grouped = [];

        foreach ($costTypes->whereNotNull('chart_account_id') as $type) {
            $grouped[$type->chart_account_id][] = $this->costSubAccount($type);
        }

        foreach ($revenueTypes as $type) {
            $grouped[$type->chart_account_id][] = $this->revenueSubAccount($type);
        }

        foreach ($treasuryTypes as $type) {
            $grouped[$type->chart_account_id][] = $this->treasurySubAccount($type);
        }

        return $grouped;
    }

    /**
     * Natural, case-insensitive order by code (`60.2` before `60.10`).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function sortByCode(array $items): array
    {
        usort($items, static fn (array $a, array $b): int => strnatcasecmp((string) $a['code'], (string) $b['code']));

        return $items;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function abilities(User $actor): array
    {
        $resolve = static fn (string $resource): array => [
            'create' => $actor->can("{$resource}.create"),
            'update' => $actor->can("{$resource}.update"),
            'delete' => $actor->can("{$resource}.delete"),
            'view_activity' => $actor->can("{$resource}.viewActivity"),
        ];

        return [
            'chart_accounts' => [
                ...$resolve('chart-accounts'),
                'export' => $actor->can('chart-accounts.export'),
                'import' => $actor->can('chart-accounts.import'),
            ],
            'revenue_types' => $resolve('revenue-types'),
            'cost_types' => $resolve('cost-types'),
            'treasury_types' => $resolve('treasury-types'),
        ];
    }
}
