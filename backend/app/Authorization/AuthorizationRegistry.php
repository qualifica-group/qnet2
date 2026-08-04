<?php

declare(strict_types=1);

namespace App\Authorization;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\CustomFieldProvider;
use App\CustomFields\FieldTypeRegistry;
use App\FieldChangeRequests\ProtectedFieldRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{resource}` string → its ResourceAuthorization. Mirrors
 * App\Tables\TableRegistry: an explicit config map (config/authorization.php)
 * resolved through the container, so a definition's dependencies are
 * injected. Adding a resource = one class + one config line.
 *
 * Unknown resource → ModelNotFoundException → 404 (via BaseApiController).
 */
class AuthorizationRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the ResourceAuthorization for the given resource key.
     *
     * @throws ModelNotFoundException when the resource is not registered.
     */
    public function resolve(string $resource): ResourceAuthorization
    {
        /** @var array<string, class-string<ResourceAuthorization>> $definitions */
        $definitions = config('authorization.definitions', []);

        $class = $definitions[$resource] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException)->setModel(ResourceAuthorization::class, [$resource]);
        }

        /** @var ResourceAuthorization $authorization */
        $authorization = $this->container->make($class);

        // Protected fields wrap FIRST (inner), custom fields SECOND (outer):
        // MetaController::fieldDescriptors() type-checks
        // `instanceof CustomFieldAwareAuthorization` to decide whether to
        // include customFieldDescriptors() — that check must keep seeing
        // CustomFieldAwareAuthorization as the OUTERMOST layer, so a
        // protected-fields resource that is ALSO custom-fieldable (e.g.
        // request-management) does not silently lose its custom-field
        // descriptors. The restriction itself is order-independent (the
        // protected-fields registry only ever names native FieldDefinition
        // keys, never a `custom.*` one), so this ordering costs nothing.
        $authorization = $this->decorateWithProtectedFields($resource, $authorization);

        return $this->decorateWithCustomFields($resource, $authorization);
    }

    /**
     * Graft custom fields onto $authorization (spec 0021) when its resource
     * is custom-fieldable — zero per-module code, see
     * CustomFieldAwareAuthorization. The 'custom-fields' admin resource
     * itself is excluded to avoid decorating the module that DEFINES custom
     * fields with custom fields of its own.
     */
    private function decorateWithCustomFields(string $resource, ResourceAuthorization $authorization): ResourceAuthorization
    {
        if ($resource === 'custom-fields') {
            return $authorization;
        }

        $entityRegistry = $this->container->make(CustomFieldEntityRegistry::class);

        if (! $entityRegistry->isCustomFieldable($resource)) {
            return $authorization;
        }

        return new CustomFieldAwareAuthorization(
            $authorization,
            $this->container->make(CustomFieldProvider::class),
            $this->container->make(FieldTypeRegistry::class),
            $this->container->make(FieldPermissionRepository::class),
            $resource,
        );
    }

    /**
     * Restrict $authorization's protected fields (spec 0078, D-1) to
     * visible+readonly for an actor lacking their dedicated permission — zero
     * per-module code, see ProtectedFieldAwareAuthorization. A resource with
     * no protected fields declared in config/field-change-requests.php skips
     * the wrap entirely (AC-005's no-regression guarantee).
     */
    private function decorateWithProtectedFields(string $resource, ResourceAuthorization $authorization): ResourceAuthorization
    {
        $registry = $this->container->make(ProtectedFieldRegistry::class);

        if ($registry->forResource($resource) === []) {
            return $authorization;
        }

        return new ProtectedFieldAwareAuthorization($authorization, $registry);
    }

    /**
     * The registered resource keys — the "form-module" resources that own a
     * form and authorization metadata (users, roles, business-functions, …).
     * Single source of truth for which permission prefixes are directly
     * assignable from the Role form (see AssignablePermissionCatalogue).
     *
     * @return array<int, string>
     */
    public function resourceKeys(): array
    {
        return array_keys(config('authorization.definitions', []));
    }
}
