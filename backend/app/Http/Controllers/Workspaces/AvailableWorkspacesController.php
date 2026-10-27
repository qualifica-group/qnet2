<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Services\Workspaces\WorkspaceAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/workspaces/available (spec 0317): any authenticated user.
 */
class AvailableWorkspacesController extends BaseApiController
{
    public function __invoke(Request $request, WorkspaceAccessService $access): JsonResponse
    {
        return $this->ok($access->available($request->user()));
    }
}
