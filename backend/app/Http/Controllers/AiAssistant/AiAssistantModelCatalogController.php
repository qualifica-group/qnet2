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
use Throwable;

/**
 * Models and providers offered to the super-admin (spec 0433), read by the backend
 * from OpenRouter; the browser never calls OpenRouter.
 */
class AiAssistantModelCatalogController extends BaseApiController
{
    private const int SERVICE_UNAVAILABLE = 503;

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
            $search = mb_strtolower(trim((string) $request->validated('search')));
            $offset = (int) $request->validated('offset', 0);
            $limit = (int) $request->validated('limit', self::DEFAULT_LIMIT);
            $selected = $this->settings->current()->model_id;

            $models = array_values(array_filter(
                $this->catalog->models(),
                fn (array $model): bool => $search === ''
                    || str_contains(mb_strtolower($model['id'].' '.$model['name']), $search),
            ));

            $items = array_map(
                fn (array $model): array => $model + ['selected' => $model['id'] === $selected],
                array_slice($models, $offset, $limit),
            );

            return $this->paginatedResponse($items, count($models), $offset, $limit);
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
        return $this->fail(__('The model list is not available. Try again later.'), self::SERVICE_UNAVAILABLE);
    }
}
