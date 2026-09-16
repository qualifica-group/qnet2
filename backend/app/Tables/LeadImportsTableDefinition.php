<?php

namespace App\Tables;

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use App\Tables\LeadImports\ImportRunUserColumn;
use App\Tables\LeadImports\LeadImportColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `import-runs` domain (spec 0034 — renamed from
 * `lead-imports`, module extraction): every lead import run, served through
 * the generic backend-driven table engine (SSRM) so the history renders as the
 * same AG Grid table as every other module.
 *
 * `baseQuery` scopes to the `leads` resource only. Runs are NOT owner-scoped:
 * every `leads.import` holder sees every run, and the derived `user` column
 * names the operator who started it (user decision 2026-09-16).
 *
 * `authorizeViewAny` is not overridden: AbstractTableDefinition's default
 * (`Gate::allows('viewAny', ImportRun::class)`) resolves through
 * ImportRunPolicy, which now checks the lead module's `leads.import` ability
 * (the former dedicated `import-runs.*` set was removed 2026-07-17).
 */
class LeadImportsTableDefinition extends AbstractTableDefinition
{
    /** The `import_runs.resource` key this table is scoped to. */
    private const RESOURCE = 'leads';

    public function __construct(
        private readonly ImportRunUserColumn $userColumn,
    ) {}

    public function domain(): string
    {
        return 'import-runs';
    }

    /**
     * @return class-string<ImportRun>
     */
    public function modelClass(): string
    {
        return ImportRun::class;
    }

    /**
     * Every run for the `leads` resource, eager-loading the operator so mapRow
     * reads the `user` column from memory (no N+1).
     *
     * @return Builder<ImportRun>
     */
    public function baseQuery(): Builder
    {
        return ImportRun::query()
            ->where('resource', self::RESOURCE)
            ->with('user');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return LeadImportColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return LeadImportColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return LeadImportColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Badge metadata for the `status` column, driven by ImportStatus (color +
     * source label). The frontend localizes the label from `enumKeyFor`.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== 'status') {
            return null;
        }

        return array_map(static fn ($meta): array => $meta->toArray(), ImportStatus::options());
    }

    /**
     * The `status` badge label is localized on the frontend from its own i18n
     * resources (`enums.import_status.<value>`).
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'status' ? 'import_status' : null;
    }

    /**
     * Map an ImportRun to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ImportRun $row */
        return [
            'id' => $row->id,
            'created_at' => $row->created_at,
            'user' => $this->userSummary($row->user),
            'original_filename' => $row->original_filename,
            'total_rows' => $row->total_rows,
            'imported_rows' => $row->imported_rows,
            'invalid_rows' => $row->invalid_rows,
            'status' => $row->status->value,
        ];
    }

    /**
     * The operator summary carrying the inline avatar (data URI) so the shared
     * UserCell renders a real avatar — mirrors QuotesTableDefinition::userSummary().
     *
     * @return array{id: int, name: string, avatar_url: string|null}
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }

    /**
     * Derived `user` set filter (operator name); every real column falls
     * through to the generic engine.
     *
     * @param  Builder<ImportRun>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $columnId === 'user' && $this->userColumn->applyFilter($query, $filter);
    }

    /**
     * @param  Builder<ImportRun>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'user') {
            return false;
        }

        $this->userColumn->applySort($query, $direction);

        return true;
    }

    /**
     * @param  Builder<ImportRun>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $columnId === 'user' ? $this->userColumn->distinctValues($query, $search, $limit) : null;
    }

    /**
     * `view` reopens the run in the wizard (available to any actor that reached
     * a row, since the table is `leads.import`-gated). `delete` is exposed when
     * ImportRunPolicy allows it — the same gate the generic bulk-delete engine
     * re-checks server-side.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = ['view'];

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        return $allowed;
    }
}
