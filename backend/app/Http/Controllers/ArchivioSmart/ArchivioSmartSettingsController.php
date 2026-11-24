<?php

declare(strict_types=1);

namespace App\Http\Controllers\ArchivioSmart;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ArchivioSmart\SaveArchivioSmartSettingsRequest;
use App\Http\Resources\ArchivioSmartSettingResource;
use App\Models\DocumentChecklist;
use App\Services\ArchivioSmart\ArchivioSmartSettingsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * ArchivioSmart connection (base URL + token) of the document checklists
 * (spec 0403, D-2). Every action requires `document-checklists.manageConnection`.
 */
class ArchivioSmartSettingsController extends BaseApiController
{
    use AuthorizesRequests;

    private const UNPROCESSABLE = 422;

    public function __construct(private readonly ArchivioSmartSettingsService $service) {}

    /**
     * GET /api/archivio-smart/settings
     */
    public function show(): JsonResponse
    {
        try {
            $this->authorize('manageConnection', DocumentChecklist::class);

            return $this->ok(new ArchivioSmartSettingResource($this->service->current()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/archivio-smart/settings
     */
    public function update(SaveArchivioSmartSettingsRequest $request): JsonResponse
    {
        try {
            $this->authorize('manageConnection', DocumentChecklist::class);

            return $this->ok(new ArchivioSmartSettingResource(
                $this->service->save($request->baseUrl(), $request->newToken(), $request->changesWebhookSecret(), $request->newWebhookSecret()),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/archivio-smart/settings/test
     */
    public function test(): JsonResponse
    {
        try {
            $this->authorize('manageConnection', DocumentChecklist::class);

            $result = $this->service->test();

            if ($result === null) {
                return $this->fail(__('archivio_smart.connection_not_configured'), self::UNPROCESSABLE);
            }

            return $this->ok($result);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
