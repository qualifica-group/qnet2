<?php

namespace App\Http\Controllers\Version;

use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Http\JsonResponse;

/**
 * PUBLIC deploy probe. GET /api/version exposes the identifier of the currently
 * deployed backend build so the SPA can detect that the API was redeployed
 * under a session still running the previous client and prompt a reload.
 *
 * SECURITY: unauthenticated by design — a client whose session already expired
 * must still be able to learn that it is outdated. The payload is a single
 * opaque deploy identifier from server config (config/app.php <- APP_VERSION),
 * never request input and never user-, tenant- or permission-scoped. No rate
 * limit: this is not a credentials endpoint (backend.md 2).
 */
class VersionController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $version = trim((string) config('app.version', ''));

        // no-store, not just no-cache: an intermediary caching this response
        // would freeze the client on the pre-deploy value and defeat the check.
        return $this->ok(['version' => $version === '' ? null : $version])
            ->header('Cache-Control', 'no-store');
    }
}
