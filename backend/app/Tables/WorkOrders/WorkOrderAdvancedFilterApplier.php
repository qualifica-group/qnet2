<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderAssignmentScope;
use App\Models\User;
use App\Products\ProductCategoryBranches;
use App\Services\Assignment\ActorCompetenceReach;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The derived advanced filters of the `work-orders` domain: a commessa has no
 * category or client of its own, it reaches both through its source offers
 * (WorkOrderSourceQuotes, the same rule as WorkOrderVisibilityScope).
 */
final class WorkOrderAdvancedFilterApplier
{
    /** Same whereIn cardinality cap as the generic AdvancedFilterApplier. */
    private const int MAX_IDS = 500;

    /** The WorkOrder relation behind each role of the "Assegnazione" filter. */
    private const array ROLE_RELATIONS = [
        WorkOrderAssignmentScope::Supervisor->value => 'supervisors',
        WorkOrderAssignmentScope::Participant->value => 'participants',
    ];

    public function __construct(private readonly ProductCategoryBranches $branches) {}

    /**
     * The "Categoria madre" preset of the Commesse page: the roots of the
     * branches the actor's competence touches. An actor with no competence, or
     * with every category, has nothing to preset and starts on "Tutte" (as
     * spec 0193 D-2 does for the category tabs).
     *
     * @return array<int, int>
     */
    public function defaultParentCategories(?User $actor): array
    {
        $reach = $actor === null ? null : ActorCompetenceReach::for($actor);

        if ($reach === null || $reach->all || $reach->isEmpty()) {
            return [ProductCategoryForSelectResolver::ALL_ROOTS_ID];
        }

        $rootIds = array_values(array_unique(array_map($this->branches->rootIdOf(...), $reach->categoryIds)));
        sort($rootIds);

        return $rootIds;
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
            WorkOrderAdvancedFilterCatalog::ASSIGNMENT => $this->applyAssignment($query, $value, $actor),
            WorkOrderAdvancedFilterCatalog::PARENT_CATEGORY => $this->applyParentCategories($query, $this->ids($value)),
            WorkOrderAdvancedFilterCatalog::PRODUCT_CATEGORY => $this->applyRevenueCategories($query, $this->ids($value)),
            WorkOrderAdvancedFilterCatalog::REGISTRY => $this->applyRegistries($query, $this->ids($value)),
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
     * The picked roots with their whole branches; the "Tutte" option lifts
     * the filter.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, int>  $rootIds
     */
    private function applyParentCategories(Builder $query, array $rootIds): void
    {
        if (! in_array(ProductCategoryForSelectResolver::ALL_ROOTS_ID, $rootIds, true)) {
            $this->applyRevenueCategories($query, $this->branchIds($rootIds));
        }
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

    /**
     * Every picked root with all its descendants.
     *
     * @param  array<int, int>  $rootIds
     * @return array<int, int>
     */
    private function branchIds(array $rootIds): array
    {
        $branchIds = [];

        foreach ($rootIds as $rootId) {
            $branchIds = [...$branchIds, ...$this->branches->branchIds($rootId)];
        }

        return array_values(array_unique($branchIds));
    }

    /**
     * @return array<int, int>
     */
    private function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_slice(array_map(intval(...), array_values(array_filter($value, is_numeric(...)))), 0, self::MAX_IDS);
    }
}
