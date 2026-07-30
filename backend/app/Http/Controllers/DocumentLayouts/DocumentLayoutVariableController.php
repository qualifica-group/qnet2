<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentLayouts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentLayouts\DocumentLayoutVariableRequest;
use App\Models\DocumentLayout;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/document-layouts/variables — the variable catalogue (`{category.key}`
 * tokens) for one `module`, masked for the current actor's field permissions
 * (D-6, spec 0069).
 *
 * Thin invokable controller: validation (DocumentLayoutVariableRequest),
 * server-side authorization (document-layouts.viewAny via
 * DocumentLayoutPolicy — same resource-level ability as for-select, no new
 * permission), catalogue lookup, envelope response.
 *
 * @see DocumentLayoutVariableCatalog::categoriesFor
 */
class DocumentLayoutVariableController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentLayoutVariableCatalog $catalog) {}

    public function __invoke(DocumentLayoutVariableRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', DocumentLayout::class);

            $module = $request->module();

            return $this->ok([
                'module' => $module->value,
                'categories' => $this->catalog->categoriesFor($module, $request->user()),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
