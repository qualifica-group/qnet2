<?php

namespace App\Http\Controllers\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
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
     * palette. `inherited` carries the shared `all` layout on an authoring
     * load of a per-mode scope, so the configurator can show what that mode
     * currently inherits without a second request; it is null everywhere
     * else (spec 0062, D3 revised).
     */
    public function show(AttributeLayoutQueryRequest $request, ProductCategory $productCategory): JsonResponse
    {
        try {
            $this->authorize('view', $productCategory);

            $context = $request->context();

            // Authoring (configurator) asks for the exact scope's own row; the
            // product form omits `exact` and gets the shared-layout fallback so
            // one saved layout drives every mode (spec 0062 revised).
            [$layout, $inherited] = $request->exact()
                ? $this->authoredLayout($productCategory, $context, $request->scope())
                : [$this->service->resolveWithFallback($productCategory, $context, $request->formMode()), null];

            return $this->ok([
                'layout' => $layout,
                'inherited' => $inherited,
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
                $request->scope(),
                $request->layout(),
            );

            return $this->ok(['layout' => $layout], 'Updated');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productCategory' => $productCategory->id]);
        }
    }

    /**
     * The authoring pair for one scope: its own row, and — for a per-mode
     * scope — the shared layout it falls back to while it has none.
     *
     * @return array{0: array{sections: array<int, array<string, mixed>>}|null, 1: array{sections: array<int, array<string, mixed>>}|null}
     */
    private function authoredLayout(ProductCategory $productCategory, AttributeContext $context, LayoutFormScope $scope): array
    {
        return [
            $this->service->resolveExact($productCategory, $context, $scope),
            $scope === LayoutFormScope::All
                ? null
                : $this->service->resolveExact($productCategory, $context, LayoutFormScope::All),
        ];
    }
}
