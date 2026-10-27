<?php

namespace App\Http\Controllers\Navigation;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\NavigationItemResource;
use App\Services\Workspaces\WorkspaceAccessService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends BaseApiController
{
    public function __construct(private readonly WorkspaceAccessService $access) {}

    /**
     * Backend-driven navigation for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['workspace' => ['sometimes', 'nullable', 'string', 'max:40']]);

        try {
            $slug = $request->query('workspace');
            $items = $slug === null || $slug === ''
                ? $this->access->fullMenu($request->user())
                : $this->access->menu($request->user(), $slug);
        } catch (ModelNotFoundException) {
            return $this->fail('Workspace not found', 404);
        }

        return $this->ok(NavigationItemResource::collection($items));
    }
}
