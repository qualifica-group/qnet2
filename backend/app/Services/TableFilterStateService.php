<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTableFilter;
use App\Tables\TableDefinition;

/**
 * Per-user table filter state: merge into config (read), upsert / reset (write).
 * Sibling of TablePreferenceService (ADR-0004) for the AG Grid filterModel, so
 * filters survive a page reload.
 *
 * Domain-agnostic: it operates ONLY through the resolved TableDefinition and the
 * generic user_table_filters store, so every table inherits filter persistence
 * with no per-domain code.
 *
 * SECURITY: the stored filterModel is restricted to the definition's FILTERABLE
 * columns (filterableColumnIds) on every write and every read, so a stale/removed
 * column can never leak back into the grid, and the persisted keys always match
 * the same allow-list the SSRM query engine enforces (TableRowsRequest). Unlike
 * column preferences this is NOT a sparse delta — filters have no default to diff
 * against, so the applied model is stored (and cleared) as a whole.
 */
class TableFilterStateService
{
    /**
     * Merge the actor's saved filter state into a resolved config so the frontend
     * can re-apply it on mount. Adds keys mirroring the preferences merge:
     *  - `filterState`: the saved filterModel (object; `{}` when none);
     *  - `filtersCustomized`: whether there is a saved filter to reset;
     *  - `appliedAdvancedFilters` (spec 0032): the saved advanced filters
     *    (object) or null when none — OVERWRITES the `null` placeholder
     *    AbstractTableDefinition::resolveConfig() emits.
     *
     * @param  array<string, mixed>  $config  the definition's resolveConfig() output
     * @return array<string, mixed>
     */
    public function applyTo(array $config, TableDefinition $definition, User $actor): array
    {
        $row = $this->rowFor($definition, $actor);

        $saved = $this->allowlistFilters($definition, $row?->filters ?? []);
        $savedAdvanced = $this->allowlistAdvanced($definition, $row?->advanced_filters ?? []);

        // Emit as an object so an empty state serializes to `{}` (a valid empty
        // AG Grid filterModel), never `[]`.
        $config['filterState'] = (object) $saved;
        $config['filtersCustomized'] = $saved !== [];
        $config['appliedAdvancedFilters'] = $savedAdvanced !== [] ? (object) $savedAdvanced : null;

        return $config;
    }

    /**
     * Persist the actor's applied filterModel and/or advanced filters for
     * this domain (idempotent upsert on (user_id, domain)). Keys outside the
     * respective allow-list are dropped defensively (the FormRequest already
     * 422s them).
     *
     * Each argument is independently optional: null (the key was ABSENT from
     * the request) leaves what is persisted for it untouched; an array (even
     * empty) replaces it. Both empty after allow-listing clears the saved
     * state entirely, so there is never an orphan row.
     *
     * @param  array<string, mixed>|null  $filterModel
     * @param  array<string, mixed>|null  $advancedFilters
     */
    public function save(TableDefinition $definition, User $actor, ?array $filterModel, ?array $advancedFilters = null): void
    {
        $stored = $filterModel === null || $advancedFilters === null ? $this->rowFor($definition, $actor) : null;

        $filtered = $this->allowlistFilters($definition, $filterModel ?? $stored?->filters ?? []);
        $filteredAdvanced = $this->allowlistAdvanced($definition, $advancedFilters ?? $stored?->advanced_filters ?? []);

        if ($filtered === [] && $filteredAdvanced === []) {
            $this->reset($definition, $actor);

            return;
        }

        UserTableFilter::query()->updateOrCreate(
            ['user_id' => $actor->id, 'domain' => $definition->domain()],
            ['filters' => $filtered, 'advanced_filters' => $filteredAdvanced],
        );
    }

    /**
     * Reset the actor's filter state for this table by removing the saved row.
     * Explicit user action only — filters are never auto-deleted.
     */
    public function reset(TableDefinition $definition, User $actor): void
    {
        UserTableFilter::query()
            ->where('user_id', $actor->id)
            ->where('domain', $definition->domain())
            ->delete();
    }

    /**
     * The actor's stored row for this domain, if any.
     */
    private function rowFor(TableDefinition $definition, User $actor): ?UserTableFilter
    {
        return UserTableFilter::query()
            ->where('user_id', $actor->id)
            ->where('domain', $definition->domain())
            ->first();
    }

    /**
     * Keep only the entries whose column id is whitelisted for filtering by the
     * definition — the same allow-list the SSRM query engine enforces.
     *
     * @param  array<string, mixed>|null  $filterModel
     * @return array<string, mixed>
     */
    private function allowlistFilters(TableDefinition $definition, ?array $filterModel): array
    {
        $allowed = array_flip($definition->filterableColumnIds());

        return array_intersect_key($filterModel ?? [], $allowed);
    }

    /**
     * Keep only the entries whose name is whitelisted in the definition's
     * advanced-filter catalogue (spec 0032) — mirrors allowlistFilters().
     *
     * @param  array<string, mixed>|null  $advancedFilters
     * @return array<string, mixed>
     */
    private function allowlistAdvanced(TableDefinition $definition, ?array $advancedFilters): array
    {
        $allowed = array_flip($definition->advancedFilterableIds());

        return array_intersect_key($advancedFilters ?? [], $allowed);
    }
}
