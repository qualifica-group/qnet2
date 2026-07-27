<?php

namespace App\Tables;

use App\CustomFields\FieldTypeRegistry;
use App\Models\Attribute;
use App\Models\User;
use App\Tables\Attributes\AttributeColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `attributes` domain (spec 0017, aligned to the
 * custom fields' presentation shape — spec 0021).
 *
 * `code`/`name`/`type`/`created_at` are real DB columns handled entirely by
 * the generic engine, mirroring CustomFieldsTableDefinition: `type` is
 * rendered as a badge whose value list comes from FieldTypeRegistry — the
 * same source of truth the write pipeline validates against — not a PHP enum
 * (OCP: a new type is one handler class + one config line, never a new case
 * here). Being a plain string column (no backed-enum cast), the generic
 * engine's default distinctValues()/enumKeyFor() already work correctly, so
 * neither is overridden here.
 */
class AttributesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly FieldTypeRegistry $fieldTypeRegistry) {}

    public function domain(): string
    {
        return 'attributes';
    }

    /**
     * @return class-string<Attribute>
     */
    public function modelClass(): string
    {
        return Attribute::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives AttributePolicy::viewAny
    // from modelClass() (attributes.viewAny).

    /**
     * @return Builder<Attribute>
     */
    public function baseQuery(): Builder
    {
        // options_count rides along in mapRow() only — not part of the
        // frozen column contract, no filter/sort ever targets it.
        return Attribute::query()->withCount('options');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return AttributeColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return AttributeColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return AttributeColumnCatalog::actions();
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
     * Badge metadata for the `type` column, driven by FieldTypeRegistry. The
     * label is the i18n key of the shared field-type catalogue (the same copy
     * the custom fields admin uses — one catalogue, one set of labels); the
     * frontend localizes it through `enumKeyFor()` below.
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
     * The `type` badge is not backed by a PHP enum (the catalogue is
     * config-driven, config/custom-fields.php), but the frontend still owns its
     * copy: declaring the enum key lets BOTH the cell badge and the Set Filter
     * checklist localize from `enums.custom_field_type.<value>` instead of
     * rendering the raw backend label key.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'type' ? FieldTypeRegistry::ENUM_KEY : null;
    }

    /**
     * Map an Attribute to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Attribute $row */
        return [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'options_count' => (int) $row->options_count,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via AttributePolicy.
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
