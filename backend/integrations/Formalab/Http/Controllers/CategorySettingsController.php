<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Integrations\Formalab\Http\Requests\UpdateCategorySettingsRequest;
use Integrations\Formalab\Services\CategorySettingsService;
use Throwable;

/**
 * The Formalab section of a product category (spec 0423). Authorization is
 * the category's own `view` / `update` policy.
 */
class CategorySettingsController extends BaseApiController
{
    public function __construct(private readonly CategorySettingsService $service) {}

    /**
     * GET /api/integrations/formalab/product-categories/{productCategory}.
     */
    public function show(ProductCategory $productCategory): JsonResponse
    {
        try {
            Gate::authorize('view', $productCategory);

            return $this->respond($productCategory);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/integrations/formalab/product-categories/{productCategory}.
     */
    public function update(UpdateCategorySettingsRequest $request, ProductCategory $productCategory): JsonResponse
    {
        try {
            Gate::authorize('update', $productCategory);

            $this->service->update($productCategory, $request->enabled(), $request->fieldMap(), $request->user());

            return $this->respond($productCategory);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function respond(ProductCategory $category): JsonResponse
    {
        return $this->ok([
            ...$this->service->show($category),
            'can_manage' => Gate::allows('update', $category),
        ]);
    }
}
