<?php

namespace App\Tables;

use App\Models\Tag;
use App\Models\User;
use App\Tables\Tags\TagColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `tags` domain (spec 0019).
 *
 * Both columns (name, created_at) are real DB columns handled entirely by
 * the generic engine — no derived column, mirroring SourcesTableDefinition.
 */
class TagsTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'tags';
    }

    /**
     * @return class-string<Tag>
     */
    public function modelClass(): string
    {
        return Tag::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TagPolicy::viewAny from
    // modelClass() (tags.viewAny).

    /**
     * @return Builder<Tag>
     */
    public function baseQuery(): Builder
    {
        return Tag::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TagColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TagColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TagColumnCatalog::actions();
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
     * Map a Tag to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Tag $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via TagPolicy.
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
