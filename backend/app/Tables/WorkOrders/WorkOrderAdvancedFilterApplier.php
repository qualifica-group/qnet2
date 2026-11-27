<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Models\User;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use App\Tables\Shared\ParentCategoryListModule;
use App\Tables\Shared\PresetListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The derived advanced filters of the `work-orders` domain: a commessa has no
 * category or client of its own, it reaches both through its source offers
 * (WorkOrderSourceQuotes, the same rule as WorkOrderVisibilityScope).
 *
 * The default parent category of a limited actor (every root of theirs) keeps
 * the commesse where they are Responsabile or Partecipante whatever their
 * category (task 431114, 2026-11-20): being assigned a commessa outside one's
 * competence must not hide it from the list. A pick narrows to the picked roots alone.
 *
 * The month and the users are the legacy Commesse filters (user request
 * 2026-11-27): the month is the start date's in the current year, as in QNet;
 * a user matches as Responsabile or as Partecipante.
 */
final class WorkOrderAdvancedFilterApplier
{
    public function __construct(private readonly PresetListFilters $presets) {}

    /**
     * The filters of the Commesse page: the legacy QNet set with the parent
     * category preset. Which commesse the actor sees is WorkOrderVisibilityScope's
     * alone (user directive 2026-11-12, no "Assegnazione" filter).
     *
     * @return array<int, array<string, mixed>>
     */
    public function commessePageFilters(): array
    {
        return array_map(
            static fn (array $filter): array => $filter['name'] === PresetListFilters::PARENT_CATEGORY ? PresetListFilters::presetParentCategory($filter) : $filter,
            WorkOrderAdvancedFilterCatalog::commessePageFilters(),
        );
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
            PresetListFilters::PARENT_CATEGORY => $this->applyParentCategory($query, $value, $actor),
            PresetListFilters::PRODUCT_CATEGORY => $this->applyRevenueCategories($query, PresetListFilters::ids($value)),
            WorkOrderAdvancedFilterCatalog::REGISTRY => $this->applyRegistries($query, PresetListFilters::ids($value)),
            WorkOrderAdvancedFilterCatalog::MONTH => $this->applyMonth($query, $value),
            WorkOrderAdvancedFilterCatalog::TEAM_MEMBERS => $this->applyTeamMembers($query, PresetListFilters::ids($value)),
        };

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyParentCategory(Builder $query, mixed $value, ?User $actor): void
    {
        $categoryIds = $this->presets->branchIds($value, $actor, ParentCategoryListModule::WorkOrders) ?? [];

        if ($actor === null || $categoryIds === [] || ! $this->presets->isActorDefault($value, $actor, ParentCategoryListModule::WorkOrders)) {
            $this->applyRevenueCategories($query, $categoryIds);

            return;
        }

        $query->where(fn (Builder $default) => $default
            ->whereIn('work_orders.id', WorkOrderSourceQuotes::withRevenueCategory($categoryIds))
            ->orWhere(fn (Builder $member) => WorkOrderVisibilityScope::whereMember($member, $actor)));
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
     * A range on the start date, not MONTH(): keeps `work_orders_start_date_index` usable.
     *
     * @param  Builder<Model>  $query
     */
    private function applyMonth(Builder $query, mixed $value): void
    {
        $month = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => WorkOrderAdvancedFilterCatalog::MONTHS_IN_YEAR]]);

        if ($month === false) {
            return;
        }

        $start = Carbon::create(null, $month, 1);

        $query->whereBetween('work_orders.start_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, int>  $userIds
     */
    private function applyTeamMembers(Builder $query, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $query->where(static fn (Builder $team) => $team
            ->whereHas('supervisors', static fn (Builder $supervisors) => $supervisors->whereKey($userIds))
            ->orWhereHas('participants', static fn (Builder $participants) => $participants->whereKey($userIds)));
    }
}
