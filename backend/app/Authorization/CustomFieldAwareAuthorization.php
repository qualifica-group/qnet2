<?php

declare(strict_types=1);

namespace App\Authorization;

use App\CustomFields\CustomFieldProvider;
use App\CustomFields\FieldTypeRegistry;
use App\CustomFields\Types\EnumDisplay;
use App\Models\CustomFieldDefinition;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decorator (spec 0021 — INNESTO READ/GRID/META/PERMESSI) that grafts a
 * resource's active custom fields onto an inner ResourceAuthorization, at
 * zero per-module cost: `AuthorizationRegistry::resolve()` wraps every
 * custom-fieldable resource in this class, so `fields()`/`fieldPermissions()`
 * transparently include `custom.<key>` alongside the native catalogue.
 *
 * Every ResourceAuthorization method is delegated to $inner UNCHANGED except
 * `fields()` and `fieldPermissions()`, which merge in the custom fields:
 * - `fields()` adds one FieldDefinition per active definition.
 * - `fieldPermissions()` keeps $inner's native result (FINAL, untouched) and
 *   computes a permission for each custom key with the SAME ceiling+DB-matrix
 *   intersect semantics as AbstractResourceAuthorization::fieldPermissions()
 *   — duplicated here (not inherited: that method is final and this class
 *   does not extend the abstract) because a custom field's ceiling is always
 *   the same default (visibleEditable, required from its own `validation`),
 *   never a per-field contextual rule like a concrete Authorization class
 *   would define.
 */
final class CustomFieldAwareAuthorization implements ResourceAuthorization
{
    public function __construct(
        private readonly ResourceAuthorization $inner,
        private readonly CustomFieldProvider $provider,
        private readonly FieldTypeRegistry $fieldTypeRegistry,
        private readonly FieldPermissionRepository $fieldPermissionRepository,
        private readonly string $resource,
    ) {}

    public function resource(): string
    {
        return $this->inner->resource();
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        $definitions = $this->provider->definitionsFor($this->resource);

        if ($definitions->isEmpty()) {
            return $this->inner->fields();
        }

        return [
            ...$this->inner->fields(),
            ...$definitions->map($this->toFieldDefinition(...))->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return $this->inner->actions();
    }

    /**
     * @return array<string, bool>
     */
    public function resourcePermissions(User $actor, ?Model $model): array
    {
        return $this->inner->resourcePermissions($actor, $model);
    }

    /**
     * @return array<string, FieldPermission>
     */
    public function fieldPermissions(User $actor, ?Model $model): array
    {
        $native = $this->inner->fieldPermissions($actor, $model);
        $definitions = $this->provider->definitionsFor($this->resource);

        if ($definitions->isEmpty()) {
            return $native;
        }

        $custom = $this->customFieldPermissions($actor, $definitions);

        return [...$native, ...$custom];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return $this->inner->actionPermissions($actor, $model);
    }

    /**
     * The enriched FieldDescriptor fragment for every active custom field on
     * this resource (spec 0021, `GET /meta/{resource}`, AC-007): the
     * definition's own metadata columns merged with its FieldTypeHandler's
     * `toMeta()` (type/config/options|relation). Consumed by MetaController
     * to replace the minimal FieldDefinition::toArray() shape for custom keys
     * only — native fields are untouched.
     *
     * @return array<int, array<string, mixed>>
     */
    public function customFieldDescriptors(): array
    {
        return $this->provider->definitionsFor($this->resource)
            ->map($this->describeCustomField(...))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function describeCustomField(CustomFieldDefinition $definition): array
    {
        $handler = $this->fieldTypeRegistry->resolve($definition->type);

        return [
            'key' => $this->provider->namespacedKey($definition->key),
            'label' => $definition->label,
            'description' => $definition->description,
            'help_text' => $definition->help_text,
            'placeholder' => $definition->placeholder,
            'icon' => $definition->icon,
            'group' => $definition->group,
            'tab' => $definition->tab,
            'sort_order' => $definition->sort_order,
            'source' => 'custom',
            'mandatory' => $this->isRequired($definition),
            ...$handler->toMeta($definition),
        ];
    }

    private function toFieldDefinition(CustomFieldDefinition $definition): FieldDefinition
    {
        return new FieldDefinition(
            $this->provider->namespacedKey($definition->key),
            $this->formControlType($definition),
            $definition->group,
            $this->isRequired($definition),
        );
    }

    /**
     * Form-control hint for the generic FieldDefinition catalogue (mirrors the
     * native `select`/`multiselect`/`number` conventions used across every
     * other Authorization class), derived from the definition's raw `type`
     * plus its enum/relation cardinality config. The RICH type used by the
     * frontend renderer is the handler's own `toMeta()['type']`
     * (`describeCustomField()`), not this hint.
     */
    private function formControlType(CustomFieldDefinition $definition): string
    {
        return match ($definition->type) {
            'integer', 'decimal' => 'number',
            'enum', 'relation' => $this->isMultiValued($definition) ? 'multiselect' : 'select',
            default => $definition->type,
        };
    }

    private function isMultiValued(CustomFieldDefinition $definition): bool
    {
        return match ($definition->type) {
            'enum' => EnumDisplay::isMultiValue($definition->config),
            'relation' => ($definition->relation_target['cardinality'] ?? 'one') === 'many',
            default => false,
        };
    }

    private function isRequired(CustomFieldDefinition $definition): bool
    {
        return (bool) ($definition->validation['required'] ?? false);
    }

    /**
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     * @return array<string, FieldPermission>
     */
    private function customFieldPermissions(User $actor, Collection $definitions): array
    {
        $isPrivileged = $actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
        $dbConfig = $isPrivileged ? null : $this->fieldPermissionRepository->forRoleIds($this->roleIds($actor));

        $permissions = [];

        foreach ($definitions as $definition) {
            $key = $this->provider->namespacedKey($definition->key);
            $ceiling = FieldPermission::visibleEditable(required: $this->isRequired($definition));

            $permissions[$key] = $isPrivileged
                ? $ceiling
                : $this->mergeFieldPermission($ceiling, $dbConfig?->get("{$this->resource}.{$key}"));
        }

        return $permissions;
    }

    /**
     * Same intersect semantics as
     * AbstractResourceAuthorization::mergeFieldPermission() (spec 0006):
     * `$db === null` (no row for this actor's roles) passes the ceiling
     * through unchanged; otherwise visible/editable AND-intersect and
     * required OR-merges (only meaningful while still editable).
     *
     * @param  array{visible: bool, editable: bool, required: bool}|null  $db
     */
    private function mergeFieldPermission(FieldPermission $ceiling, ?array $db): FieldPermission
    {
        if ($db === null) {
            return $ceiling;
        }

        $visible = $ceiling->visible && $db['visible'];
        $editable = $ceiling->editable && $db['editable'];
        $required = $ceiling->required || ($db['required'] && $editable);

        return match (true) {
            ! $visible => FieldPermission::hidden(),
            ! $editable => FieldPermission::visibleReadonly(),
            default => FieldPermission::visibleEditable(required: $required),
        };
    }

    /**
     * @return array<int, int>
     */
    private function roleIds(User $actor): array
    {
        return $actor->roles->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }
}
