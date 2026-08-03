<?php

declare(strict_types=1);

namespace App\Http\Controllers\Authorization;

use App\Authorization\PermissionCatalogueBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/authorization/permission-catalogue (spec 0076) — the Area > Module
 * tree feeding the Role form's two-panel permission explorer: assignable
 * permissions and fields (native + custom) grouped by the real navigation
 * taxonomy, built by PermissionCatalogueBuilder.
 *
 * Authorization: `roles.viewAny` OR `roles.create` OR `roles.update` — wider
 * than FieldCatalogueController's (create/update only) because the explorer
 * also backs the read-only role detail view (spec 0076 scope).
 */
class PermissionCatalogueController extends BaseApiController
{
    public function __construct(private readonly PermissionCatalogueBuilder $builder) {}

    public function index(Request $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $this->authorizeViewsCatalogue($actor);

            return $this->ok(['areas' => $this->builder->build()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeViewsCatalogue(User $actor): void
    {
        if (! $actor->can('roles.viewAny') && ! $actor->can('roles.create') && ! $actor->can('roles.update')) {
            throw new AuthorizationException;
        }
    }
}
