<?php

namespace App\Services;

use App\Enums\FilterViewVisibility;
use App\Models\TableFilterView;
use App\Models\User;
use App\Services\Table\CustomFilterRuleValidator;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saved filter views (spec 0007): list (own + others' shared), create, update,
 * delete. Domain-agnostic: operates ONLY through the resolved TableDefinition
 * and the generic table_filter_views store, so every table inherits saved
 * views with no per-domain code.
 *
 * SECURITY: `filters` is restricted to the definition's FILTERABLE columns on
 * every write (defensive re-filter — TableFilterViewRequest already 422s any
 * out-of-whitelist key) AND on every read, so a stale/removed column can never
 * leak back into the grid and a stored view can never widen the SSRM filter
 * allow-list (mirrors TableFilterStateService).
 */
class TableFilterViewService
{
    /**
     * The actor's own views (private + shared) plus other users' `shared`
     * views for the domain. Order (spec 0158 contract): the actor's
     * favorites first (name asc), then their own remaining views (name asc),
     * then other users' `shared` views (name asc) — a stable sort over a
     * name-ordered query gives the "name asc" tiebreak within each group for
     * free (no raw SQL needed). `is_favorite` is resolved for every row in
     * ONE extra correlated-EXISTS query (withExists), never N+1.
     *
     * @return Collection<int, TableFilterView>
     */
    public function list(TableDefinition $definition, User $actor): Collection
    {
        $views = TableFilterView::query()
            ->with('user')
            ->withExists(['favoritedByUsers as is_favorite' => fn (Builder $query) => $query->where('user_id', $actor->id)])
            ->where('domain', $definition->domain())
            ->where(function ($query) use ($actor): void {
                $query->where('user_id', $actor->id)
                    ->orWhere('visibility', FilterViewVisibility::Shared->value);
            })
            ->orderBy('name')
            ->get();

        $sorted = $views->sortBy(fn (TableFilterView $view): int => match (true) {
            (bool) $view->is_favorite => 0,
            $view->user_id === $actor->id => 1,
            default => 2,
        })->values();

        return $sorted->each(fn (TableFilterView $view) => $this->reFilter($definition, $view));
    }

    /**
     * Create a new view owned by $actor. `rules` present (non-null, spec
     * 0158) makes it a "custom filter" view: `filters`/`advancedFilters` are
     * saved empty regardless of what is submitted.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $advancedFilters
     * @param  array{and?: array<int, mixed>, or?: array<int, mixed>}|null  $rules
     */
    public function create(
        TableDefinition $definition,
        User $actor,
        string $name,
        array $filters,
        FilterViewVisibility $visibility,
        array $advancedFilters = [],
        ?array $rules = null,
    ): TableFilterView {
        $normalizedRules = $this->reFilterRules($definition, $rules);

        $view = TableFilterView::query()->create([
            'user_id' => $actor->id,
            'domain' => $definition->domain(),
            'name' => $name,
            'filters' => $normalizedRules !== null ? [] : $this->allowlist($definition, $filters),
            'visibility' => $visibility,
            'advanced_filters' => $normalizedRules !== null ? [] : $this->allowlistAdvanced($definition, $advancedFilters),
            'rules' => $normalizedRules,
        ]);

        return $this->reFilter($definition, $view);
    }

    /**
     * Update an existing view (full replace of name/filters/visibility/
     * advanced filters/rules).
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $advancedFilters
     * @param  array{and?: array<int, mixed>, or?: array<int, mixed>}|null  $rules
     */
    public function update(
        TableDefinition $definition,
        TableFilterView $view,
        string $name,
        array $filters,
        FilterViewVisibility $visibility,
        array $advancedFilters = [],
        ?array $rules = null,
    ): TableFilterView {
        $normalizedRules = $this->reFilterRules($definition, $rules);

        $view->update([
            'name' => $name,
            'filters' => $normalizedRules !== null ? [] : $this->allowlist($definition, $filters),
            'visibility' => $visibility,
            'advanced_filters' => $normalizedRules !== null ? [] : $this->allowlistAdvanced($definition, $advancedFilters),
            'rules' => $normalizedRules,
        ]);

        return $this->reFilter($definition, $view->refresh());
    }

    public function delete(TableFilterView $view): void
    {
        $view->delete();
    }

    /**
     * Mark $view favorite for $actor (spec 0158, D-4). Idempotent:
     * re-favoriting an already-favorited view is a no-op (syncWithoutDetaching
     * never duplicates, and the pivot's own unique index backs it).
     */
    public function favorite(TableDefinition $definition, TableFilterView $view, User $actor): TableFilterView
    {
        $view->favoritedByUsers()->syncWithoutDetaching([$actor->id]);

        return $this->reFilter($definition, $view->fresh());
    }

    /**
     * Unmark $view favorite for $actor. Idempotent: detaching an
     * already-absent pivot row is a no-op DELETE.
     */
    public function unfavorite(TableDefinition $definition, TableFilterView $view, User $actor): TableFilterView
    {
        $view->favoritedByUsers()->detach($actor->id);

        return $this->reFilter($definition, $view->fresh());
    }

    /**
     * Re-filter a fetched view's `filters`/`advanced_filters`/`rules` in
     * place to the definition's current allow-lists, so a removed/renamed
     * column never reaches the frontend even for a view saved before the
     * change.
     */
    private function reFilter(TableDefinition $definition, TableFilterView $view): TableFilterView
    {
        $view->filters = $this->allowlist($definition, $view->filters ?? []);
        $view->advanced_filters = $this->allowlistAdvanced($definition, $view->advanced_filters ?? []);
        $view->rules = $this->reFilterRules($definition, $view->rules);

        return $view;
    }

    /**
     * Drop every rule whose `field` is no longer a filterable column, or no
     * longer resolves to a usable rule type (spec 0158 contract: "regole con
     * campo non più filtrabile/usabile vengono scartate"). `null` in, or
     * both groups left empty, normalizes to `null` out — never `{and: [],
     * or: []}` (spec: "se non ne resta nessuna, rules = null").
     *
     * @param  array{and?: array<int, mixed>, or?: array<int, mixed>}|null  $rules
     * @return array{and: array<int, mixed>, or: array<int, mixed>}|null
     */
    private function reFilterRules(TableDefinition $definition, ?array $rules): ?array
    {
        if ($rules === null) {
            return null;
        }

        $filterable = $definition->filterableColumnMap();

        $filterGroup = function (mixed $group) use ($filterable): array {
            if (! is_array($group)) {
                return [];
            }

            return array_values(array_filter($group, static function ($rule) use ($filterable): bool {
                if (! is_array($rule) || ! is_string($rule['field'] ?? null)) {
                    return false;
                }

                $config = $filterable[$rule['field']] ?? null;

                return $config !== null && CustomFilterRuleValidator::resolveType($config) !== null;
            }));
        };

        $and = $filterGroup($rules['and'] ?? []);
        $or = $filterGroup($rules['or'] ?? []);

        return $and === [] && $or === [] ? null : ['and' => $and, 'or' => $or];
    }

    /**
     * Keep only the entries whose column id is whitelisted for filtering by
     * the definition — the same allow-list the SSRM query engine enforces.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function allowlist(TableDefinition $definition, array $filters): array
    {
        $allowed = array_flip($definition->filterableColumnIds());

        return array_intersect_key($filters, $allowed);
    }

    /**
     * Keep only the entries whose name is whitelisted in the definition's
     * advanced-filter catalogue (spec 0032) — mirrors allowlist().
     *
     * @param  array<string, mixed>  $advancedFilters
     * @return array<string, mixed>
     */
    private function allowlistAdvanced(TableDefinition $definition, array $advancedFilters): array
    {
        $allowed = array_flip($definition->advancedFilterableIds());

        return array_intersect_key($advancedFilters, $allowed);
    }
}
