<?php

namespace App\Http\Middleware;

use App\Enums\HttpStatusEnum;
use App\Services\McpLinks\McpLinkService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/auth/refresh deletes the token and issues a new one that is not tied
 * to the MCP link, so a revocation or a credential change would no longer reach
 * it. A token issued for a link is therefore never refreshed: the connector
 * exchanges the link credential again instead.
 */
class RejectMcpLinkTokenRefresh
{
    private const string REFRESH_PATH = 'api/auth/refresh';

    public function __construct(private readonly McpLinkService $links) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is(self::REFRESH_PATH)) {
            $token = $request->user('sanctum')?->currentAccessToken();

            if ($token instanceof PersonalAccessToken && $this->links->issuedFor($token)) {
                return response()->json([
                    'success' => false,
                    'message' => __('A token issued for an MCP link cannot be refreshed.'),
                ], HttpStatusEnum::FORBIDDEN->value);
            }
        }

        return $next($request);
    }
}
