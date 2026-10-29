<?php

declare(strict_types=1);

namespace App\Http\Controllers\Authorization;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldDefinition;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET /api/authorization/fields — the field catalogue for every resource
 * registered in config/authorization.php (spec 0006), feeding the Role
 * form's field-permission matrix section.
 *
 * Authorization: `roles.create` OR `roles.update` (you manage roles) —
 * distinct from the per-resource `{resource}.viewAny` gate MetaController
 * uses, since this endpoint is a role-management tool, not a per-resource
 * metadata read.
 */
class FieldCatalogueController extends BaseApiController
{
    public function __construct(private readonly AuthorizationRegistry $registry) {}

    public function index(Request $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $this->authorizeManagesRoles($actor);

            return $this->ok(['resources' => $this->catalogue()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @return array<int, array{resource: string, fields: array<int, array<string, mixed>>}>
     */
    private function catalogue(): array
    {
        /** @var array<string, class-string> $definitions */
        $definitions = config('authorization.definitions', []);

        return array_map(
            fn (string $resource): array => [
                'resource' => $resource,
                'fields' => array_map(
                    static fn (FieldDefinition $field): array => $field->toArray(),
                    $this->registry->resolve($resource)->fields(),
                ),
            ],
            array_keys($definitions),
        );
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeManagesRoles(User $actor): void
    {
        Gate::allowIf($actor->can('roles.create') || $actor->can('roles.update'));
    }
}
