<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmailTemplates;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EmailTemplates\EmailTemplateVariableRequest;
use App\Models\User;
use App\Services\OutboundEmails\EmailOwner;
use App\Services\OutboundEmails\EmailOwnerRegistry;
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
 * `document-layouts.viewAny`), catalogue lookup (by module, via the EmailOwnerRegistry), envelope response.
 *
 * @see EmailOwner::variableCatalog
 */
class EmailTemplateVariableController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly EmailOwnerRegistry $owners) {}

    public function __invoke(EmailTemplateVariableRequest $request): JsonResponse
    {
        try {
            $this->authorize('email-templates.view');

            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->owners->forModule($request->module())->variableCatalog($actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
