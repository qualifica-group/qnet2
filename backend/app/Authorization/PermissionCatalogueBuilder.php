<?php

declare(strict_types=1);

namespace App\Authorization;

use App\CustomFields\CustomFieldProvider;

/**
 * Builds the Area > Module tree for `GET /api/authorization/permission-catalogue`
 * (spec 0076): the two-panel Role-form explorer's single source for its
 * taxonomy, permissions and fields (native + custom) per module.
 *
 * The only real taxonomy is `config/navigation.php`. An area is either a
 * navigation GROUP (its direct children are the modules) or a permissioned
 * TOP-LEVEL entry, which is a module in its own right and forms an area of
 * one. A module is included only when it also has assignable permissions
 * (AssignablePermissionCatalogue); the permission-only resources with no menu
 * entry (`notes`, `attachments`) are appended as a trailing "shared" area.
 */
final class PermissionCatalogueBuilder
{
    /**
     * Canonical action order; anything outside this list is appended
     * alphabetically (spec 0076 data_contract).
     */
    private const array CANONICAL_ABILITIES = [
        'viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity',
    ];

    private const string SHARED_AREA_KEY = 'shared';

    private const string SHARED_AREA_LABEL_KEY = 'permissions.areas.shared';

    public function __construct(
        private readonly AuthorizationRegistry $registry,
        private readonly AssignablePermissionCatalogue $catalogue,
        private readonly CustomFieldProvider $customFields,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(): array
    {
        // Step 1: group every assignable permission by its resource prefix.
        $permissionsByResource = $this->catalogue->namesByResource();

        // Step 2: one area per navigation group, modules from its direct children.
        $areas = $this->areasFromNavigation($permissionsByResource);

        // Step 3: permission-only resources (no menu entry) form the trailing area.
        $shared = $this->sharedArea($permissionsByResource);

        if ($shared !== null) {
            $areas[] = $shared;
        }

        return $areas;
    }

    /**
     * @param  array<string, array<int, string>>  $permissionsByResource
     * @return array<int, array<string, mixed>>
     */
    private function areasFromNavigation(array $permissionsByResource): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = config('navigation.items', []);

        $areas = [];

        foreach ($items as $item) {
            // A GROUP contributes its direct children; a CHILDLESS top-level
            // entry is itself the module and forms an area of one. Skipping
            // the latter outright is what kept spec 0101's Task — the first
            // top-level entry the app ever had that carried a resource
            // permission — out of the Role form entirely (AC-055): its
            // permissions existed, were assignable, and could still never be
            // granted from the UI. `dashboard` is unaffected: it carries no
            // `permission`, so resourcesFromItems() drops it as before.
            $resources = $this->resourcesFromItems(
                $item['children'] ?? [$item],
                $permissionsByResource,
            );

            if ($resources === []) {
                continue;
            }

            $areas[] = [
                'key' => $item['key'],
                'label_key' => $item['label'],
                'resources' => $resources,
            ];
        }

        return $areas;
    }

    /**
     * Turn navigation entries into catalogue modules: a group's DIRECT
     * children, or the single top-level entry that is a module itself.
     * Deeper children (e.g. `imports` under `leads`) never reach here — they
     * share their parent's permission prefix and are not modules of their own
     * (spec 0076 context).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<int, string>>  $permissionsByResource
     * @return array<int, array<string, mixed>>
     */
    private function resourcesFromItems(array $items, array $permissionsByResource): array
    {
        $resources = [];

        foreach ($items as $child) {
            $permission = $child['permission'] ?? null;

            if (! is_string($permission) || $permission === '') {
                // No permission gate (e.g. `migrations`, role-gated only): not
                // a permission-catalogue module.
                continue;
            }

            $resource = $this->resourcePrefixOf($permission);

            if (! isset($permissionsByResource[$resource])) {
                // A module in navigation with no assignable permissions of its
                // own does not appear (spec 0076 data_contract).
                continue;
            }

            $resources[] = [
                'resource' => $resource,
                'label_key' => $child['label'],
                'permissions' => $this->orderedPermissions($permissionsByResource[$resource]),
                'fields' => $this->fieldsFor($resource),
            ];
        }

        return $resources;
    }

    /**
     * @param  array<string, array<int, string>>  $permissionsByResource
     * @return array<string, mixed>|null
     */
    private function sharedArea(array $permissionsByResource): ?array
    {
        $resources = [];

        foreach ($this->catalogue->permissionOnlyResources() as $resource) {
            if (! isset($permissionsByResource[$resource])) {
                continue;
            }

            $resources[] = [
                'resource' => $resource,
                'label_key' => "permissions.resources.{$resource}",
                'permissions' => $this->orderedPermissions($permissionsByResource[$resource]),
                'fields' => [],
            ];
        }

        return $resources === [] ? null : [
            'key' => self::SHARED_AREA_KEY,
            'label_key' => self::SHARED_AREA_LABEL_KEY,
            'resources' => $resources,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fieldsFor(string $resource): array
    {
        // Native fields carry no admin-entered label (the frontend already
        // owns their i18n key); a custom field's is free text decided at
        // runtime by the admin, so it must travel with the payload — pulled
        // here, not from FieldDefinition (CustomFieldAwareAuthorization
        // deliberately drops it, shared with MetaController/field-permission
        // enforcement, out of this feature's blast radius).
        $customLabels = $this->customFieldLabels($resource);

        return array_map(
            static fn (FieldDefinition $field): array => [
                ...$field->toArray(),
                'custom' => str_starts_with($field->key, CustomFieldProvider::KEY_PREFIX),
                'label' => $customLabels[$field->key] ?? null,
            ],
            $this->registry->resolve($resource)->fields(),
        );
    }

    /**
     * Active custom field labels for $resource, keyed by their namespaced
     * field key (`custom.<key>`) so fieldsFor() can look them up by
     * FieldDefinition::$key directly.
     *
     * @return array<string, string>
     */
    private function customFieldLabels(string $resource): array
    {
        $labels = [];

        foreach ($this->customFields->definitionsFor($resource) as $definition) {
            $labels[$this->customFields->namespacedKey($definition->key)] = $definition->label;
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array{name: string, ability: string}>
     */
    private function orderedPermissions(array $names): array
    {
        $permissions = array_map(
            fn (string $name): array => ['name' => $name, 'ability' => $this->abilityOf($name)],
            $names,
        );

        usort(
            $permissions,
            fn (array $a, array $b): int => $this->abilityRank($a['ability']) <=> $this->abilityRank($b['ability'])
                ?: $a['ability'] <=> $b['ability'],
        );

        return $permissions;
    }

    private function abilityRank(string $ability): int
    {
        $index = array_search($ability, self::CANONICAL_ABILITIES, true);

        return $index === false ? count(self::CANONICAL_ABILITIES) : $index;
    }

    /**
     * The action half of a `{resource}.{ability}` permission name.
     */
    private function abilityOf(string $permission): string
    {
        $dot = strpos($permission, '.');

        return $dot === false ? $permission : substr($permission, $dot + 1);
    }

    /**
     * The resource half of a `{resource}.{ability}` navigation permission gate.
     */
    private function resourcePrefixOf(string $permission): string
    {
        $dot = strpos($permission, '.');

        return $dot === false ? $permission : substr($permission, 0, $dot);
    }
}
