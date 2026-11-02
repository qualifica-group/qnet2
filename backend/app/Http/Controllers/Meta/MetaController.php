<?php

declare(strict_types=1);

namespace App\Http\Controllers\Meta;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\CustomFieldAwareAuthorization;
use App\Authorization\FieldDefinition;
use App\Authorization\ResourceAuthorization;
use App\Authorization\ResourcePermissionsBuilder;
use App\CustomFields\CustomFieldProvider;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET /api/meta/{resource} — generic, registry-driven authorization metadata
 * (spec 0004). One controller serves every resource registered in
 * config/authorization.php, mirroring TableController's fail-closed pattern:
 * unknown {resource} → 404 (AuthorizationRegistry::resolve), missing
 * `{resource}.viewAny` → 403.
 *
 * Returns the create-context field catalogue (`data.fields`) plus the full
 * `permissions` block computed with model = null.
 */
class MetaController extends BaseApiController
{
    public function __construct(
        private readonly AuthorizationRegistry $registry,
        private readonly ResourcePermissionsBuilder $builder,
    ) {}

    public function show(Request $request, string $resource): JsonResponse
    {
        try {
            $authorization = $this->registry->resolve($resource); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();

            // Fail-closed: the standard `{resource}.viewAny` permission (the
            // same one BasePolicy::viewAny checks), mirroring
            // TableController::authorizeViewAny. Resources without a viewAny
            // permission (RDA, ODA: browsing is gated by `view`) fall back to it.
            $this->authorizeViewAny($actor->can("{$resource}.viewAny") || $actor->can("{$resource}.view"));

            $fields = $this->fieldDescriptors($authorization);

            $permissions = $this->builder->build($authorization, $actor, null);

            return $this->okWithPermissions(['fields' => $fields], $permissions);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['resource' => $resource]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeViewAny(bool $allowed): void
    {
        Gate::allowIf($allowed);
    }

    /**
     * The `data.fields` catalogue: native fields keep their current minimal
     * FieldDefinition::toArray() shape (unchanged); custom fields (spec 0021,
     * AC-007) are replaced by their enriched descriptor
     * (CustomFieldAwareAuthorization::customFieldDescriptors(): label,
     * description, config, options/relation, source:'custom', …). Detected by
     * the `custom.` key prefix rather than a second decorator call, so the
     * two lists never disagree on which keys are custom.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fieldDescriptors(ResourceAuthorization $authorization): array
    {
        $native = array_values(array_filter(
            $authorization->fields(),
            static fn (FieldDefinition $field): bool => ! str_starts_with($field->key, CustomFieldProvider::KEY_PREFIX),
        ));

        $descriptors = array_map(static fn (FieldDefinition $field): array => $field->toArray(), $native);

        if ($authorization instanceof CustomFieldAwareAuthorization) {
            $descriptors = [...$descriptors, ...$authorization->customFieldDescriptors()];
        }

        return $descriptors;
    }
}
