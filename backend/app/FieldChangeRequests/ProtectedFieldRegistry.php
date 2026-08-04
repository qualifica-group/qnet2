<?php

declare(strict_types=1);

namespace App\FieldChangeRequests;

/**
 * Reads `config/field-change-requests.php` (spec 0078, D-1) and resolves it
 * into `ProtectedField` value objects. Bind as a container singleton (see
 * AppServiceProvider) — the same reasoning as `CustomFieldEntityRegistry`:
 * this is consulted on every field-permission resolution
 * (`ProtectedFieldAwareAuthorization`) as well as by `permissions:sync`, so
 * the config parse is memoized once per request rather than once per call
 * site.
 */
class ProtectedFieldRegistry
{
    /**
     * @var array<string, array<string, ProtectedField>>|null
     */
    private ?array $map = null;

    /**
     * Every protected field declared for the given resource, keyed by field
     * name. Empty array when the resource has none (or is unknown) —
     * required for AC-005's no-regression guarantee.
     *
     * @return array<string, ProtectedField>
     */
    public function forResource(string $resource): array
    {
        return $this->map()[$resource] ?? [];
    }

    public function find(string $resource, string $field): ?ProtectedField
    {
        return $this->forResource($resource)[$field] ?? null;
    }

    /**
     * Every permission the protected-fields config generates, for
     * `SyncPermissions`'s third source.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->map() as $fields) {
            foreach ($fields as $field) {
                $permissions[] = $field->permission();
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return array<int, string>
     */
    public function resources(): array
    {
        return array_keys($this->map());
    }

    /**
     * @return array<string, array<string, ProtectedField>>
     */
    private function map(): array
    {
        return $this->map ??= $this->build();
    }

    /**
     * @return array<string, array<string, ProtectedField>>
     */
    private function build(): array
    {
        $map = [];

        foreach (config('field-change-requests.resources', []) as $resource => $definition) {
            $fields = [];

            foreach ($definition['fields'] ?? [] as $field => $fieldConfig) {
                $fields[$field] = new ProtectedField(
                    resource: $resource,
                    field: $field,
                    ability: $fieldConfig['ability'],
                    column: $fieldConfig['column'],
                    fieldLabel: $fieldConfig['label'],
                    resourceLabel: $definition['label'],
                    recordPath: $definition['record_path'],
                );
            }

            $map[$resource] = $fields;
        }

        return $map;
    }
}
