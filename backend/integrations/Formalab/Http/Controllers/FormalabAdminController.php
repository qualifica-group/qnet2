<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Http\JsonResponse;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Jobs\SyncFormalabClasses;
use Integrations\Formalab\Services\FormalabClient;
use Integrations\Formalab\Services\LinkBackfill;
use Integrations\Formalab\Services\StatusReport;
use Integrations\Formalab\Support\Trigger;
use Throwable;

/**
 * The super-admin side of the integration: status page data and its actions.
 * Authorization is the `super-admin` route middleware.
 */
class FormalabAdminController extends BaseApiController
{
    public function __construct(
        private readonly StatusReport $statusReport,
        private readonly FormalabClient $client,
        private readonly LinkBackfill $backfill,
    ) {}

    /**
     * GET /api/integrations/formalab/status.
     */
    public function status(): JsonResponse
    {
        try {
            return $this->ok($this->statusReport->handle());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/integrations/formalab/sync: queues the catalogue sync.
     */
    public function sync(): JsonResponse
    {
        try {
            SyncFormalabClasses::dispatch(Trigger::Manual);

            return $this->ok(null, __('formalab::messages.sync_queued'), HttpStatusEnum::ACCEPTED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/integrations/formalab/test-connection: a live probe, never an error status.
     */
    public function testConnection(): JsonResponse
    {
        try {
            $startedAt = microtime(true);

            try {
                $this->client->get('/api/classi', ['per_page' => 1], Trigger::Manual);
            } catch (FormalabException $exception) {
                return $this->ok(['ok' => false, 'latency_ms' => null, 'message' => $exception->getMessage()]);
            }

            return $this->ok(['ok' => true, 'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000), 'message' => null]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/integrations/formalab/link-work-orders: the automatic backfill.
     */
    public function linkWorkOrders(): JsonResponse
    {
        try {
            return $this->ok($this->backfill->handle());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
