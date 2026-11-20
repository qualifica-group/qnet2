<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Integrations\IntegrationRegistry;
use Illuminate\Http\JsonResponse;

class IntegrationController extends BaseApiController
{
    public function __construct(private readonly IntegrationRegistry $registry) {}

    /**
     * GET /api/integrations: the keys of the active optional integrations.
     */
    public function __invoke(): JsonResponse
    {
        return $this->ok(['active' => $this->registry->active()]);
    }
}
