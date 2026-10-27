<?php

namespace App\Http\Requests\Workspaces;

/**
 * PUT /api/workspaces/{workspace} (spec 0317): same shape as the store request;
 * the slug uniqueness ignores the workspace itself.
 */
class UpdateWorkspaceRequest extends StoreWorkspaceRequest {}
