<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmailTemplates;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EmailTemplates\EmailTemplateVariableRequest;
use App\Services\OutboundEmails\WorkOrderEmailVariableCatalog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/email-templates/variables — the `{category.key}` variable
 * catalogue for one `module` (spec 0175, D-4), feeding the "Modelli email"
 * form's and the Commessa email composer's variable picker.
 *
 * Thin invokable controller: validation (EmailTemplateVariableRequest),
 * server-side authorization (`email-templates.view`, no dedicated
 * permission — mirrors DocumentLayoutVariableController's use of
 * `document-layouts.viewAny`), catalogue lookup, envelope response.
 *
 * @see WorkOrderEmailVariableCatalog::categoriesFor
 */
class EmailTemplateVariableController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderEmailVariableCatalog $catalog) {}

    public function __invoke(EmailTemplateVariableRequest $request): JsonResponse
    {
        try {
            $this->authorize('email-templates.view');

            return $this->ok($this->catalog->categoriesFor($request->user()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
