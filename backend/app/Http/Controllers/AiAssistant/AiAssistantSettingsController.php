<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AiAssistant\UpdateAiAssistantSettingsRequest;
use App\Http\Resources\AiAssistant\AiAssistantSettingsResource;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use App\Services\AiAssistant\Settings\OpenRouterCatalogUnavailableException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Settings of the AI assistant (spec 0433). Authorization is the `super-admin`
 * route middleware: no assignable permission.
 */
class AiAssistantSettingsController extends BaseApiController
{
    public function __construct(private readonly AiAssistantSettingsService $settings) {}

    /**
     * GET /api/ai-assistant/settings
     */
    public function show(): JsonResponse
    {
        try {
            return $this->ok(new AiAssistantSettingsResource($this->settings->current()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/ai-assistant/settings
     */
    public function update(UpdateAiAssistantSettingsRequest $request): JsonResponse
    {
        try {
            return $this->ok(new AiAssistantSettingsResource(
                $this->settings->update($request->validated(), $request->user()),
            ));
        } catch (OpenRouterCatalogUnavailableException) {
            return $this->fail(__('The model list is not available. Try again later.'), Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
