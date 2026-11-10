<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderAssignmentScope;
use App\Models\User;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use App\Tables\Shared\PresetListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The derived advanced filters of the `work-orders` domain: a commessa has no
 * category or client of its own, it reaches both through its source offers
 * (WorkOrderSourceQuotes, the same rule as WorkOrderVisibilityScope).
 */
final class WorkOrderAdvancedFilterApplier
{
    /** The WorkOrder relation behind each role of the "Assegnazione" filter. */
    private const array ROLE_RELATIONS = [
        WorkOrderAssignmentScope::Supervisor->value => 'supervisors',
        WorkOrderAssignmentScope::Participant->value => 'participants',
    ];

    public function __construct(private readonly PresetListFilters $presets) {}

    /**
     * The filters of the Commesse page: the domain's catalogue plus the two
     * presets it opens with.
     *
     * @return array<int, array<string, mixed>>
     */
    public function commessePageFilters(?User $actor): array
    {
        $filters = array_map(
            fn (array $filter): array => $filter['name'] === PresetListFilters::PARENT_CATEGORY ? $this->presets->presetParentCategory($filter, $actor) : $filter,
            WorkOrderAdvancedFilterCatalog::advancedFilters(),
        );

        return [
            PresetListFilters::assignmentFilter('workOrders', 'work_order_assignment_scope', [WorkOrderAssignmentScope::Supervisor->value, WorkOrderAssignmentScope::Participant->value], WorkOrderVisibilityScope::seesBeyondOwnRecords($actor) ? [] : [WorkOrderAssignmentScope::Visible->value]),
            ...$filters,
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return bool false when $name is not one of these filters
     */
    public function apply(Builder $query, string $name, mixed $value, ?User $actor): bool
    {
        if (! in_array($name, WorkOrderAdvancedFilterCatalog::DERIVED_FILTERS, true)) {
            return false;
        }

        match ($name) {
            PresetListFilters::ASSIGNMENT => $this->applyAssignment($query, $value, $actor),
            PresetListFilters::PARENT_CATEGORY => $this->applyRevenueCategories($query, $this->presets->branchIds($value) ?? []),
            PresetListFilters::PRODUCT_CATEGORY => $this->applyRevenueCategories($query, PresetListFilters::ids($value)),
            WorkOrderAdvancedFilterCatalog::REGISTRY => $this->applyRegistries($query, PresetListFilters::ids($value)),
        };

        return true;
    }

    /**
     * The picked roles in OR. `visible` lifts the restriction; a value naming
     * no known role matches nothing rather than leaving the list unscoped.
     *
     * @param  Builder<Model>  $query
     */
    private function applyAssignment(Builder $query, mixed $value, ?User $actor): void
    {
        $scopes = array_filter(array_map(WorkOrderAssignmentScope::tryFrom(...), array_filter((array) $value, is_string(...))));

        if (in_array(WorkOrderAssignmentScope::Visible, $scopes, true)) {
            return;
        }

        if ($scopes === [] || $actor === null) {
            $query->whereNull('work_orders.id');

            return;
        }

        $query->where(static function (Builder $roles) use ($scopes, $actor): void {
            foreach ($scopes as $scope) {
                $roles->orWhereHas(self::ROLE_RELATIONS[$scope->value], static fn (Builder $members) => $members->whereKey($actor->id));
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, int>  $categoryIds
     */
    private function applyRevenueCategories(Builder $query, array $categoryIds): void
    {
        if ($categoryIds !== []) {
            $query->whereIn('work_orders.id', WorkOrderSourceQuotes::withRevenueCategory($categoryIds));
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, int>  $registryIds
     */
    private function applyRegistries(Builder $query, array $registryIds): void
    {
        if ($registryIds !== []) {
            $query->withQuoteLineOfRegistry($registryIds);
        }
    }
}
