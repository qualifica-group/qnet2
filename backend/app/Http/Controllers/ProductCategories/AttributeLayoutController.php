<?php

namespace App\Http\Controllers\ProductCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductCategories\AttributeLayoutQueryRequest;
use App\Http\Requests\ProductCategories\UpdateAttributeLayoutRequest;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET/PUT endpoints for a category's configurable attribute layout (spec
 * 0062, data_contract) — the drag-and-drop configurator's read/write
 * contract. A dedicated controller (not folded into ProductCategoryController):
 * a distinct sub-resource with its own FormRequests/Service, mirroring the
 * `effective-attributes` endpoint's own single-purpose shape.
 *
 * Thin controller: FormRequest validation, authorization
 * (ProductCategoryPolicy — no new permission, reuses product-categories.view/
 * update), Service call, envelope response.
 *
 * @see AttributeLayoutService
 */
class AttributeLayoutController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AttributeLayoutService $service,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    /**
     * GET /api/product-categories/{productCategory}/attribute-layouts —
     * the persisted layout (or null) plus the category's effective
     * attribute catalogue for that context, feeding the configurator's
     * palette.
     */
    public function show(AttributeLayoutQueryRequest $request, ProductCategory $productCategory): JsonResponse
    {
        try {
            $this->authorize('view', $productCategory);

            $context = $request->context();

            return $this->ok([
                'layout' => $this->service->resolveForProduct($productCategory, $context, $request->formMode()),
                'attributes' => $this->hierarchy->effectiveAttributes($productCategory, $context)->values(),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productCategory' => $productCategory->id]);
        }
    }

    /**
     * PUT /api/product-categories/{productCategory}/attribute-layouts —
     * upsert (or, when `layout` is null/empty, delete) the layout for the
     * submitted (context, form_mode).
     */
    public function update(UpdateAttributeLayoutRequest $request, ProductCategory $productCategory): JsonResponse
    {
        try {
            $this->authorize('update', $productCategory);

            $layout = $this->service->upsert(
                $productCategory,
                $request->context(),
                $request->formMode(),
                $request->layout(),
            );

            return $this->ok(['layout' => $layout], 'Updated');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productCategory' => $productCategory->id]);
        }
    }
}
