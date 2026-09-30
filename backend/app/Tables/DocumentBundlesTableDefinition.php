<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\DocumentBundle;
use App\Models\User;
use App\Services\DocumentBundleService;
use App\Tables\DocumentBundles\DocumentBundleColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `document-bundles` domain (spec 0175, D-7c/D-14).
 *
 * `name`/`description`/`is_active`/`created_at` are real DB columns handled
 * entirely by the generic engine; `files_count` is an AGGREGATE column
 * (baseQuery()'s `withCount(['attachments as files_count'])`).
 */
class DocumentBundlesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly DocumentBundleService $service) {}

    public function domain(): string
    {
        return 'document-bundles';
    }

    /**
     * @return class-string<DocumentBundle>
     */
    public function modelClass(): string
    {
        return DocumentBundle::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives DocumentBundlePolicy::viewAny
    // from modelClass() (document-bundles.viewAny).

    /**
     * @return Builder<DocumentBundle>
     */
    public function baseQuery(): Builder
    {
        return DocumentBundle::query()->withCount(['attachments as files_count']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return DocumentBundleColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return DocumentBundleColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return DocumentBundleColumnCatalog::actions();
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
     * Map a DocumentBundle to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var DocumentBundle $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'files_count' => (int) $row->files_count,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via DocumentBundlePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var DocumentBundle $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
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
     * Delegate to DocumentBundleService::delete() so the generic bulk-delete
     * endpoint respects the SAME path as the single DELETE
     * /document-bundles/{documentBundle} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var DocumentBundle $model */
        $this->service->delete($model);
    }
}
