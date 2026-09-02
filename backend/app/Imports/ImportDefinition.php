<?php

namespace App\Imports;

use App\Enums\ImportDedupMode;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for a single domain's import (spec 0012, extended spec 0033).
 *
 * A definition declares EVERYTHING about its import: which columns the fixed
 * CSV template carries, how to validate one row, how to detect a natural-key
 * duplicate (against the database AND against the rest of the file), and how
 * to create one valid row via the EXISTING domain Service (never duplicated
 * creation logic here). The generic ImportRegistry + ImportController +
 * ValidateImportJob/ProcessImportJob operate ONLY through this contract, so
 * the security-critical parsing/validation/dedup engine lives in exactly one
 * place and every domain inherits it identically — adding a resource is 1
 * class + 1 config line (config/imports.php), mirroring App\Tables.
 *
 * Spec 0033 extends the contract with the mapping-driven wizard shape
 * (fields/globalConfig/recognizers/supportsExtraFields/dedupModes/
 * persistRow), all with retro-compatible defaults in AbstractImportDefinition
 * so the 5 legacy definitions keep compiling and behaving identically
 * (AC-019) — legacy columns()/validateRow()/dedupKey()/existsInDatabase()/
 * createRow() are untouched and still drive the legacy flow underneath.
 *
 * @phpstan-type ImportColumn array{id: string, required: bool}
 * @phpstan-type ImportField array{id: string, label: string, required: bool, group: ?string, type: string}
 * @phpstan-type ImportGlobalField array{id: string, label: string, required: bool, for_select_resource: ?string, multiple: bool, depends_on: ?string, default: mixed}
 * @phpstan-type ImportReviewField array{id: string, label: string}
 */
interface ImportDefinition
{
    /**
     * Permission prefix / domain key, e.g. "business-functions". Also the
     * `resource` field persisted on the ImportRun and matched against the
     * route {domain} segment for ownership.
     */
    public function domain(): string;

    /**
     * The `resource` key persisted/exposed (defaults to domain()).
     */
    public function resource(): string;

    /**
     * The Eloquent model class this import authorizes against. Drives the
     * fail-closed default authorizeImport() (via the model's Policy `import`
     * ability), so a definition can never accidentally skip the permission
     * check.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string;

    /**
     * Authorize the import action. False → 403 on every endpoint.
     *
     * Defaults (in AbstractImportDefinition) to the model's Policy `import`
     * ability (fail-closed): a definition that forgets to override still
     * requires the permission.
     */
    public function authorizeImport(User $actor): bool;

    /**
     * Ordered column catalogue: drives the downloadable CSV template header
     * (in this exact order) AND which columns are required (a blank value on
     * a required column is a validation error the definition itself reports
     * from validateRow(), typically via AbstractImportDefinition::
     * requiredColumnIds()).
     *
     * @return array<int, array{id: string, required: bool}>
     */
    public function columns(): array;

    /**
     * Validate one CSV row's OWN fields (required columns, format, geo
     * resolution, ...). Dedup (natural-key vs existing DB rows AND intra-file
     * duplicates) is NOT this method's concern — it is layered on top by
     * ImportRowProcessor via dedupKey()/existsInDatabase(), so every
     * definition gets identical, correct dedup semantics for free.
     *
     * @param  array<string, string>  $row  column id => raw CSV value (already mapped to the declared header, never re-split)
     * @return array<int, string> motivated error messages; empty = row accepted
     */
    public function validateRow(array $row, ImportRowContext $context): array;

    /**
     * The row's natural-key value for dedup (both intra-file AND existing-DB),
     * built from the row's OWN values — never a DB id. Null means this
     * definition has no meaningful key for the row (e.g. its key column is
     * blank and already reported as an error by validateRow(), or the
     * definition has no natural key at all and only dedups differently — see
     * existsInDatabase()).
     *
     * @param  array<string, string>  $row
     */
    public function dedupKey(array $row): ?string;

    /**
     * Whether a row with the given dedupKey() already exists in the database.
     * A definition with NO database natural key (e.g. operational-sites, which
     * only dedups intra-file on city+street) always returns false here —
     * intra-file dedup still applies via dedupKey(), it just never collides
     * with a persisted row.
     */
    public function existsInDatabase(string $key): bool;

    /**
     * Create ONE valid, already-deduped row by delegating to the EXISTING
     * domain Service (e.g. BusinessFunctionService/CompanyService/
     * OperationalSiteService) — CREATE ONLY, no update/upsert (spec 0012).
     * Called inside the caller's own per-row DB::transaction (ProcessImportJob),
     * so a thrown exception here isolates cleanly to this row.
     *
     * @param  array<string, string>  $row
     */
    public function createRow(User $actor, array $row): void;

    /**
     * Catalogue of MAPPABLE fields the wizard's mapping step offers a file
     * column against (in addition to `__ignore__`/`__extra__`). Defaults (in
     * AbstractImportDefinition) to one field per columns() entry (label
     * falls back to the column id; concrete definitions override with real
     * i18n label keys/groups/types when adopting the unified wizard).
     *
     * @return array<int, ImportField>
     */
    public function fields(): array;

    /**
     * Catalogue of the wizard's configuration-step global fields (values that
     * apply to every row, e.g. campaign/status for leads — never mapped from
     * a file column). Defaults to none, matching the 5 legacy domains which
     * have no global config.
     *
     * @return array<int, ImportGlobalField>
     */
    public function globalConfig(): array;

    /**
     * Row recognizers to run during staging (StageImportJob), in order, to
     * resolve values that are not a direct column mapping (e.g. splitting a
     * full name, resolving a geo name). Defaults to none.
     *
     * @return array<int, class-string>
     */
    public function recognizers(): array;

    /**
     * Whether file columns may be mapped to `__extra__` and stored verbatim
     * (keyed by original column name) instead of a declared field. Defaults
     * to false.
     */
    public function supportsExtraFields(): bool;

    /**
     * Duplicate-handling strategies this definition supports, offered to the
     * configuration step and validated against on configure. Defaults to
     * [ImportDedupMode::CreateOnly], matching the legacy create-only
     * behaviour.
     *
     * @return array<int, ImportDedupMode>
     */
    public function dedupModes(): array;

    /**
     * Create or update ONE domain record from a staged row according to the
     * given dedup strategy, using the EXISTING domain Service(s) — never
     * duplicated creation logic here. Called by ProcessImportJob inside its
     * own per-row DB::transaction, so a thrown exception isolates cleanly to
     * this row. Defaults (in AbstractImportDefinition) to delegating to the
     * legacy createRow(), ignoring $dedupStrategy — retro-compat for the 5
     * legacy domains, which are always create-only.
     *
     * `$convertToOpportunity` (spec 0045): the run's confirm-step choice —
     * defaults false, ignored by every definition that has not adopted
     * auto-convert-to-Opportunity (only `leads` has, so far).
     *
     * @param  array<string, mixed>  $globalConfig
     */
    public function persistRow(User $actor, ImportRunRow $row, array $globalConfig, string $dedupStrategy, bool $convertToOpportunity = false): void;

    /**
     * Resolve the primary-key id of the EXISTING dominant record this staged
     * row would collide with / update (for leads: the matching Registry id,
     * found by email/phone/mobile), or null when the row is new. Drives the
     * wizard's duplicate handling at staging: StageImportJob stores it on
     * import_run_rows.duplicate_of_id and maps it to a row status per the
     * chosen dedup strategy (ignore→skipped, manual→duplicate, create_new/
     * update_existing→valid). Defaults (in AbstractImportDefinition) to null:
     * the 5 legacy create-only domains keep rejecting existing rows through
     * existsInDatabase()/dedupKey(), unaffected by this.
     *
     * @param  array<string, mixed>  $mapped  field id => resolved value (after recognizers)
     */
    public function resolveDuplicate(array $mapped): ?int;

    /**
     * Resolve BOTH resolveDuplicate()'s legacy id AND, for a definition that
     * supports richer per-row duplicate reporting (spec 0036: leads' Registry
     * match with matched channels + a same-campaign lead), the review-facing
     * meta a `duplicate` row's `import_run_rows.duplicate_meta` persists.
     * Defaults (in AbstractImportDefinition) to wrapping resolveDuplicate()
     * with a null meta: every definition that has not adopted spec 0036
     * behaves identically, with no extra resolution work.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig
     * @return array{id: ?int, meta: ?array{registry_id: int, registry_name: string, lead_id: ?int, matched_on: array<int, string>}}
     */
    public function resolveDuplicateMatch(array $mapped, array $globalConfig): array;

    /**
     * Field ids without which the domain record cannot be created at all
     * (e.g. for leads: `first_name`/`last_name`, since a Registry's identity
     * card needs one). Any of these still blank after recognizers() ran is
     * defaulted by StagedRowBuilder to `config('imports.placeholder')` and
     * flags the row for review, rather than rejecting it outright (spec 0033
     * delta D-2026-07-15-placeholder-review-fields) — the placeholder stays
     * editable in the review grid. Defaults (in AbstractImportDefinition) to
     * none: the 5 legacy domains keep rejecting a blank required column as a
     * validateRow() error, unaffected by this.
     *
     * @return array<int, string>
     */
    public function requiredForCreation(): array;

    /**
     * Catalogue of the FINAL persisted fields the wizard's review grid
     * shows/edits — never the raw input columns. Defaults (in
     * AbstractImportDefinition) to fields() reduced to {id,label}. A
     * definition whose recognizers() replace an input-only field with
     * derived ones (e.g. leads' `full_name` -> `first_name`/`last_name`)
     * overrides this to drop the input-only field, so the review UI edits
     * what actually gets persisted, not what was uploaded.
     *
     * @return array<int, ImportReviewField>
     */
    public function reviewFields(): array;
}
