<?php

namespace App\Http\Controllers\CustomFields;

use App\CustomFields\CustomFieldEntityRegistry;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\CustomFieldDefinition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/custom-fields/relation-targets — every entity a RELATION field
 * (custom field or attribute) can point at: the custom-fieldable domains plus
 * the relation-only reference models such as provinces (spec 0225). Feeds the
 * admin relation target picker; the host-entity picker keeps using
 * `custom-fields/entities`. Same authorization as that sibling
 * (custom-fields.viewAny via CustomFieldDefinitionPolicy).
 */
class CustomFieldRelationTargetsController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CustomFieldEntityRegistry $registry) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', CustomFieldDefinition::class);

            return $this->ok($this->registry->relationTargets());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
