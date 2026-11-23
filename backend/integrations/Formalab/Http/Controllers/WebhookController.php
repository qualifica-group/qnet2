<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Http\JsonResponse;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Http\Requests\WebhookClassesRequest;
use Integrations\Formalab\Services\EditionRefresher;
use Integrations\Formalab\Support\Trigger;
use Throwable;

/**
 * Inbound calls from Formalab, authenticated by InboundWebhookGuard.
 */
class WebhookController extends BaseApiController
{
    private const int BAD_GATEWAY = 502;

    public function __construct(private readonly EditionRefresher $refresher) {}

    /**
     * POST /api/integrations/formalab/webhooks/classes: classes changed on Formalab.
     */
    public function classes(WebhookClassesRequest $request): JsonResponse
    {
        try {
            $ids = array_values(array_unique(array_map(intval(...), $request->validated('edition_ids'))));

            return $this->ok($this->refresher->handle($ids, Trigger::Webhook));
        } catch (FormalabException) {
            return $this->fail(__('formalab::messages.unreachable'), self::BAD_GATEWAY);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
