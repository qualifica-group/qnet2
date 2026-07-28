<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\ProductCategoryTabResource;
use App\Services\RequestManagement\RequestCategoryTabsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/request-management/product-categories (spec 0064, M3): feeds the
 * "Gestione Richieste" category tab strip — only the product categories with
 * at least one request in the actor's own D-3 scope, each with its own
 * DISTINCT request count (D-2).
 *
 * Thin invokable controller: permission gate, resolver call, Resource
 * collection. Declared as its OWN controller (not a method on
 * RequestManagementController) to keep this lane's write surface isolated
 * from the shared work-panel controller.
 *
 * @see RequestCategoryTabsResolver
 */
class ProductCategoryTabsController extends BaseApiController
{
    public function __construct(private readonly RequestCategoryTabsResolver $resolver) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.viewAny'), 403);

            $categories = $this->resolver->resolve($user);

            return $this->ok(['categories' => ProductCategoryTabResource::collection($categories)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
