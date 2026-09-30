<?php

namespace App\Tables;

use App\Models\Source;
use App\Models\User;
use App\Tables\Sources\SourceColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `sources` domain (spec 0018).
 *
 * Both columns (name, created_at) are real DB columns handled entirely by
 * the generic engine — no derived column, mirroring the simplest slice of
 * ReferentTypesTableDefinition.
 */
class SourcesTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'sources';
    }

    /**
     * @return class-string<Source>
     */
    public function modelClass(): string
    {
        return Source::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives SourcePolicy::viewAny from
    // modelClass() (sources.viewAny).

    /**
     * @return Builder<Source>
     */
    public function baseQuery(): Builder
    {
        return Source::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return SourceColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return SourceColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return SourceColumnCatalog::actions();
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
     * Map a Source to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Source $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via SourcePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
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
}
