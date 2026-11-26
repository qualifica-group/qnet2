<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AiAssistant\AiAssistantModelProvidersRequest;
use App\Http\Requests\AiAssistant\IndexAiAssistantModelsRequest;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use App\Services\AiAssistant\Settings\OpenRouterCatalog;
use App\Services\AiAssistant\Settings\OpenRouterCatalogUnavailableException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Models and providers offered to the super-admin (spec 0433), read by the backend
 * from OpenRouter; the browser never calls OpenRouter.
 */
class AiAssistantModelCatalogController extends BaseApiController
{
    private const int DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly OpenRouterCatalog $catalog,
        private readonly AiAssistantSettingsService $settings,
    ) {}

    /**
     * GET /api/ai-assistant/models
     */
    public function index(IndexAiAssistantModelsRequest $request): JsonResponse
    {
        try {
            $offset = (int) $request->validated('offset', 0);
            $limit = (int) $request->validated('limit', self::DEFAULT_LIMIT);
            $models = $this->catalog->search((string) $request->validated('search'), $this->settings->current()->model_id);

            return $this->paginatedResponse(array_slice($models, $offset, $limit), count($models), $offset, $limit);
        } catch (OpenRouterCatalogUnavailableException) {
            return $this->unavailable();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/ai-assistant/models/providers?model_id=author/slug
     */
    public function providers(AiAssistantModelProvidersRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->catalog->providers($request->validated('model_id')));
        } catch (OpenRouterCatalogUnavailableException) {
            return $this->unavailable();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function unavailable(): JsonResponse
    {
        return $this->fail(__('The model list is not available. Try again later.'), Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
