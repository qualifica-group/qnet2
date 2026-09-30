<?php

namespace App\Tables;

use App\Models\ReferentType;
use App\Models\User;
use App\Tables\ReferentTypes\ReferentTypeColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `referent-types` domain (spec 0016).
 *
 * Both columns (name, created_at) are real DB columns handled entirely by
 * the generic engine — no derived column, mirroring the simplest slice of
 * BusinessFunctionsTableDefinition.
 */
class ReferentTypesTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'referent-types';
    }

    /**
     * @return class-string<ReferentType>
     */
    public function modelClass(): string
    {
        return ReferentType::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ReferentTypePolicy::
    // viewAny from modelClass() (referent-types.viewAny).

    /**
     * @return Builder<ReferentType>
     */
    public function baseQuery(): Builder
    {
        return ReferentType::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ReferentTypeColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ReferentTypeColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ReferentTypeColumnCatalog::actions();
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
     * Map a ReferentType to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ReferentType $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via ReferentTypePolicy.
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
