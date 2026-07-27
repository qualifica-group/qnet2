<?php

namespace App\Tables;

use App\CustomFields\FieldTypeRegistry;
use App\Models\CustomFieldDefinition;
use App\Models\User;
use App\Tables\CustomFields\CustomFieldColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `custom-fields` admin domain (spec 0021 — ADMIN
 * CRUD DEFINIZIONI): the grid listing every CustomFieldDefinition across
 * every custom-fieldable entity_type.
 *
 * Every column is a real DB column, handled entirely by the generic engine;
 * `type` is rendered as a badge whose value list comes from
 * FieldTypeRegistry — the same source of truth the write pipeline validates
 * against — not a PHP enum (OCP: a new type is one handler class + one
 * config line, never a new case here), mirroring
 * AttributesTableDefinition's own `type` badge.
 */
class CustomFieldsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly FieldTypeRegistry $fieldTypeRegistry) {}

    public function domain(): string
    {
        return 'custom-fields';
    }

    /**
     * @return class-string<CustomFieldDefinition>
     */
    public function modelClass(): string
    {
        return CustomFieldDefinition::class;
    }

    /**
     * @return Builder<CustomFieldDefinition>
     */
    public function baseQuery(): Builder
    {
        return CustomFieldDefinition::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return CustomFieldColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CustomFieldColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CustomFieldColumnCatalog::actions();
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
     * Badge metadata for the `type` column, driven by FieldTypeRegistry.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== 'type') {
            return null;
        }

        return array_map(static fn (string $type): array => [
            'value' => $type,
            'label' => "customFields.types.{$type}",
            'color' => null,
            'icon' => null,
        ], $this->fieldTypeRegistry->all());
    }

    /**
     * Same rationale as AttributesTableDefinition::enumKeyFor(): the shared
     * type catalogue is config-driven, but the frontend owns its copy — so cell
     * badge AND Set Filter both localize from `enums.custom_field_type.<value>`
     * instead of showing the raw label key.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'type' ? FieldTypeRegistry::ENUM_KEY : null;
    }

    /**
     * Map a CustomFieldDefinition to the row payload. `actions` is attached by
     * the generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var CustomFieldDefinition $row */
        return [
            'id' => $row->id,
            'entity_type' => $row->entity_type,
            'key' => $row->key,
            'label' => $row->label,
            'type' => $row->type,
            'group' => $row->group,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via CustomFieldDefinitionPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
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
}
