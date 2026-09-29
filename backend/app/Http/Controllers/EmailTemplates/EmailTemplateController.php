<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmailTemplates;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EmailTemplates\StoreEmailTemplateRequest;
use App\Http\Requests\EmailTemplates\UpdateEmailTemplateRequest;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `email-templates` resource (spec 0175, D-14),
 * backing the backend-driven table row-actions (view/edit/delete) plus
 * create. List is served generically by `POST /api/tables/email-templates/rows`.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (EmailTemplatePolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see EmailTemplateService
 */
class EmailTemplateController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EmailTemplateService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/email-templates/{emailTemplate} — single email template (view row-action).
     */
    public function show(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            $this->authorize('view', $emailTemplate);

            return $this->okWithPermissions(
                new EmailTemplateResource($emailTemplate),
                $this->buildPermissions($request->user(), $emailTemplate),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['emailTemplate' => $emailTemplate->id]);
        }
    }

    /**
     * POST /api/email-templates — create a new email template.
     */
    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', EmailTemplate::class);

            $emailTemplate = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new EmailTemplateResource($emailTemplate),
                $this->buildPermissions($request->user(), $emailTemplate),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/email-templates/{emailTemplate} — update an existing email template.
     */
    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            $this->authorize('update', $emailTemplate);

            $emailTemplate = $this->service->update($emailTemplate, $request->toData());

            return $this->okWithPermissions(
                new EmailTemplateResource($emailTemplate),
                $this->buildPermissions($request->user(), $emailTemplate),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['emailTemplate' => $emailTemplate->id]);
        }
    }

    /**
     * DELETE /api/email-templates/{emailTemplate} — delete an email template.
     */
    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            $this->authorize('delete', $emailTemplate);

            $this->service->delete($emailTemplate);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['emailTemplate' => $emailTemplate->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?EmailTemplate $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('email-templates'), $actor, $model);
    }
}
