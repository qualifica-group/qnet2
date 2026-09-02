<?php

namespace App\Imports;

use App\Enums\ImportDedupMode;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Shared resolution for every concrete ImportDefinition. Mirrors
 * App\Tables\AbstractTableDefinition: a concrete definition declares only its
 * model (modelClass()), its column catalogue (columns()) and the row-level
 * hooks (validateRow/dedupKey/existsInDatabase/createRow); the cross-cutting
 * parts (resource() default, fail-closed authorization, column-id/required-id
 * derivation) live here once.
 */
abstract class AbstractImportDefinition implements ImportDefinition
{
    public function resource(): string
    {
        return $this->domain();
    }

    /**
     * Fail-closed default authorization: delegate to the model's Policy
     * `import` ability (BasePolicy::import() → "{resource}.import").
     *
     * Mirrors AbstractTableDefinition::authorizeViewAny — Gate::allows()
     * returns false when no Policy/permission is registered, never fail-open.
     */
    public function authorizeImport(User $actor): bool
    {
        return Gate::forUser($actor)->allows('import', $this->modelClass());
    }

    /**
     * Column ids in declared order — the downloadable CSV template header.
     *
     * @return array<int, string>
     */
    public function columnIds(): array
    {
        return array_map(static fn (array $column): string => $column['id'], $this->columns());
    }

    /**
     * Column ids flagged required. Concrete definitions call this from
     * validateRow() to reject a row with a blank value on any of these,
     * instead of re-declaring the required-column loop themselves.
     *
     * @return array<int, string>
     */
    public function requiredColumnIds(): array
    {
        $ids = [];

        foreach ($this->columns() as $column) {
            if (($column['required'] ?? false) === true) {
                $ids[] = $column['id'];
            }
        }

        return $ids;
    }

    /**
     * Split a pipe-delimited (`|`) multi-value CSV cell into trimmed,
     * non-blank items. The delimiter for any column that itself packs a LIST
     * into a single CSV cell (the CSV field separator is already a comma),
     * e.g. `permissions`/`roles` (RolesImportDefinition/UsersImportDefinition).
     * A blank cell yields an empty list.
     *
     * @return array<int, string>
     */
    protected function splitPipeList(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode('|', $raw)),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * Retro-compatible default: one mappable field per columns() entry. The
     * label falls back to the column id (concrete definitions adopting the
     * unified wizard override this with real i18n label keys/groups/types);
     * the 5 legacy definitions never call the wizard mapping step, so this
     * default only needs to keep the contract satisfied, not be pretty.
     *
     * @return array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>
     */
    public function fields(): array
    {
        return array_map(static fn (array $column): array => [
            'id' => $column['id'],
            'label' => $column['id'],
            'required' => $column['required'] ?? false,
            'group' => null,
            'type' => 'text',
        ], $this->columns());
    }

    /**
     * Retro-compatible default: no global configuration step fields — the
     * 5 legacy domains have no values that apply to every row.
     *
     * `multiple`/`depends_on` (spec 0094, AC-050) are additive: a scalar
     * global field (every one before `leads`' `product_ids`) declares
     * `multiple: false, depends_on: null` — no behaviour change.
     *
     * @return array<int, array{id: string, label: string, required: bool, for_select_resource: ?string, multiple: bool, depends_on: ?string, default: mixed}>
     */
    public function globalConfig(): array
    {
        return [];
    }

    /**
     * Retro-compatible default: no row recognizers run during staging.
     *
     * @return array<int, class-string>
     */
    public function recognizers(): array
    {
        return [];
    }

    /**
     * Retro-compatible default: no `__extra__` mapping target.
     */
    public function supportsExtraFields(): bool
    {
        return false;
    }

    /**
     * Retro-compatible default: only the legacy create-only strategy.
     *
     * @return array<int, ImportDedupMode>
     */
    public function dedupModes(): array
    {
        return [ImportDedupMode::CreateOnly];
    }

    /**
     * Retro-compatible default: delegate to the legacy createRow(), ignoring
     * $dedupStrategy. The 5 legacy domains are always create-only, so staged
     * rows are persisted from their mapped_values exactly as the pre-0033
     * flow persisted a validated CSV row — no behaviour change.
     *
     * @param  array<string, mixed>  $globalConfig
     */
    public function persistRow(User $actor, ImportRunRow $row, array $globalConfig, string $dedupStrategy, bool $convertToOpportunity = false): void
    {
        $this->createRow($actor, $row->mapped_values ?? []);
    }

    /**
     * Retro-compatible default: no existing dominant record is resolved by id.
     * The 5 legacy create-only domains keep rejecting DB-existing rows through
     * existsInDatabase()/dedupKey() at staging; only wizard definitions that
     * support upsert (e.g. leads → Registry match) override this.
     *
     * @param  array<string, mixed>  $mapped
     */
    public function resolveDuplicate(array $mapped): ?int
    {
        return null;
    }

    /**
     * Retro-compatible default: wraps resolveDuplicate() with a null meta —
     * only a definition that overrides this (spec 0036: leads) reports the
     * richer review-facing match.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig
     * @return array{id: ?int, meta: null}
     */
    public function resolveDuplicateMatch(array $mapped, array $globalConfig): array
    {
        return ['id' => $this->resolveDuplicate($mapped), 'meta' => null];
    }

    /**
     * Retro-compatible default: no field is required for creation — the 5
     * legacy domains keep rejecting a blank required column as a
     * validateRow() error, never through the placeholder mechanism.
     *
     * @return array<int, string>
     */
    public function requiredForCreation(): array
    {
        return [];
    }

    /**
     * Retro-compatible default: the review grid shows/edits the same fields
     * the mapping step offers, reduced to {id,label}.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function reviewFields(): array
    {
        return array_map(static fn (array $field): array => [
            'id' => $field['id'],
            'label' => $field['label'],
        ], $this->fields());
    }
}
