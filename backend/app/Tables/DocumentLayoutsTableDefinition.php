<?php

namespace App\Tables;

use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutDefaultManager;
use App\Services\DocumentLayoutService;
use App\Tables\DocumentLayouts\DocumentLayoutAdvancedFilterCatalog;
use App\Tables\DocumentLayouts\DocumentLayoutColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `document-layouts` domain (spec 0069, MT-5).
 *
 * Every column (name, code, module, is_default, is_active, description,
 * created_at, updated_at) is a real DB column handled entirely by the
 * generic engine. `is_default` is declared WITHOUT `editable` in the column
 * catalogue (DocumentLayoutColumnCatalog): its change must go through
 * DocumentLayoutDefaultManager's transactional invariant (D-7, "un solo
 * predefinito per modulo"), which the generic inline-edit pipeline's default
 * write — a bare mass-assignment `$row->update([...])`, never calling the
 * Service — would bypass entirely. `is_active` IS editable, but updateCell()
 * below routes it through the SAME guard (D-7d: a predefinito layout cannot
 * be deactivated) before persisting, exactly mirroring what
 * DocumentLayoutService::update() does for the regular PATCH endpoint.
 */
class DocumentLayoutsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly DocumentLayoutService $service,
        private readonly DocumentLayoutDefaultManager $defaultManager,
    ) {}

    public function domain(): string
    {
        return 'document-layouts';
    }

    /**
     * @return class-string<DocumentLayout>
     */
    public function modelClass(): string
    {
        return DocumentLayout::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives DocumentLayoutPolicy::viewAny
    // from modelClass() (document-layouts.viewAny).

    /**
     * @return Builder<DocumentLayout>
     */
    public function baseQuery(): Builder
    {
        return DocumentLayout::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return DocumentLayoutColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return DocumentLayoutColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return DocumentLayoutColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return DocumentLayoutAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'name', 'direction' => 'asc'],
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
     * Map a DocumentLayout to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var DocumentLayout $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'code' => $row->code,
            'module' => $row->module->value,
            'is_default' => $row->is_default,
            'is_active' => $row->is_active,
            'description' => $row->description,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via DocumentLayoutPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var DocumentLayout $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to DocumentLayoutService::delete() so the generic bulk-delete
     * endpoint respects the SAME behaviour as the single DELETE
     * /document-layouts/{documentLayout} endpoint — the D-7 predefinito
     * guard and the image cascade included.
     */
    public function deleteModel(Model $model): void
    {
        /** @var DocumentLayout $model */
        $this->service->delete($model);
    }

    /**
     * Override of the generic default cell write (ResolvesEditableColumns):
     * `is_active` is the only inline-editable column here, but a plain
     * mass-assignment update would silently bypass D-7d (a predefinito
     * layout cannot be deactivated) — the invariant lives in
     * DocumentLayoutDefaultManager, called from DocumentLayoutService for
     * the regular PATCH endpoint, and would never run on the generic
     * per-cell write path without this override. `$requestedIsDefault` is
     * always null here: `is_default` is not declared editable (see
     * DocumentLayoutColumnCatalog), so TableCellUpdateService's own
     * structural allow-list already rejects that column with a 422 before
     * this method is ever reached.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var DocumentLayout $row */
        if ($columnId === 'is_active') {
            $this->defaultManager->assertUpdateTransitionValid($row, (bool) $value, null);
        }

        $row->update([$columnId => $value]);

        return $row->fresh() ?? $row;
    }
}
