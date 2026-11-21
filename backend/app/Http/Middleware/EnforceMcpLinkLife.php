<?php

namespace App\Http\Middleware;

use App\Enums\HttpStatusEnum;
use App\Models\McpLink;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * A token issued for an MCP link (named `mcp-link-<id>`, which a refresh keeps)
 * is valid only while its link is alive: not revoked, not expired, user active,
 * password unchanged since the opening. This is the defence that does not depend
 * on the immediate deletion done by the credential-change listener.
 *
 * The marker is the token name, read from the already authenticated token. It
 * counts only when the link exists and belongs to the token's user and API
 * client: a name chosen at an ordinary login that merely looks like it leaves an
 * ordinary token. Unmarked tokens cost no query; a marked one costs one read of
 * the link by primary key.
 *
 * A marked token is never refreshed: the refresh would issue a token with the
 * same marker, but the connector exchanges the credential again instead.
 */
class EnforceMcpLinkLife
{
    private const string REFRESH_PATH = 'api/auth/refresh';

    public function handle(Request $request, Closure $next): Response
    {
        $link = $this->linkOfToken($request);

        if ($link === null) {
            return $next($request);
        }

        if (! $link->isAliveFor($request->user('sanctum'))) {
            $request->user('sanctum')->currentAccessToken()->delete();

            return response()->json([
                'success' => false,
                'message' => __('The link is no longer valid.'),
            ], HttpStatusEnum::UNAUTHORIZED->value);
        }

        if ($request->isMethod('POST') && $request->is(self::REFRESH_PATH)) {
            return response()->json([
                'success' => false,
                'message' => __('A token issued for an MCP link cannot be refreshed.'),
            ], HttpStatusEnum::FORBIDDEN->value);
        }

        return $next($request);
    }

    private function linkOfToken(Request $request): ?McpLink
    {
        // No bearer token: nothing to look up, and no authentication triggered here.
        if ($request->bearerToken() === null) {
            return null;
        }

        $token = $request->user('sanctum')?->currentAccessToken();
        $linkId = $token instanceof PersonalAccessToken ? McpLink::linkIdFromTokenName($token->name) : null;

        if ($linkId === null) {
            return null;
        }

        $link = McpLink::query()->find($linkId);

        return $link !== null
            && (int) $link->user_id === (int) $token->tokenable_id
            && (int) $link->api_client_id === (int) $token->api_client_id
            ? $link
            : null;
    }
}
