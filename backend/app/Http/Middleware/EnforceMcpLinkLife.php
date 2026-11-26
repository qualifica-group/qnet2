<?php

namespace App\Http\Middleware;

use App\Enums\HttpStatusEnum;
use App\Models\McpLink;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A token issued for an MCP link carries the ability `mcp-link:<id>`, which only
 * the exchange sets (and which any login or refresh would lose), so it cannot be
 * obtained by choosing a device name. Such a token is valid only while its link
 * is alive: not revoked, not expired, user active, password unchanged since the
 * opening. This is the defence that does not depend on the immediate deletion
 * done by the credential-change listener.
 *
 * The marker is read from the already authenticated token: unmarked tokens cost no
 * query; a marked one costs one read of the link by primary key. A marked token
 * whose link is gone or belongs to someone else is dead as well. A marked token is
 * never refreshed: the connector exchanges the credential again instead.
 */
class EnforceMcpLinkLife
{
    private const string REFRESH_PATH = 'api/auth/refresh';

    public function handle(Request $request, Closure $next): Response
    {
        // No bearer token: nothing to look up, and no authentication triggered here.
        if ($request->bearerToken() === null) {
            return $next($request);
        }

        $user = $request->user('sanctum');
        $token = $user?->currentAccessToken();
        $linkId = $token instanceof PersonalAccessToken ? McpLink::linkIdFromAbilities($token->abilities ?? []) : null;

        if ($linkId === null) {
            return $next($request);
        }

        $link = McpLink::query()->find($linkId);

        if ($link === null || ! $this->belongsTo($link, $token) || ! $link->isAliveFor($user)) {
            $this->deleteBestEffort($token);

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

        $request->attributes->set(McpLink::REQUEST_ATTRIBUTE, $link->id);

        return $next($request);
    }

    private function belongsTo(McpLink $link, PersonalAccessToken $token): bool
    {
        return (int) $link->user_id === (int) $token->tokenable_id && (int) $link->api_client_id === (int) $token->api_client_id;
    }

    /**
     * The 401 does not depend on the deletion: a dead link is refused at every request anyway.
     */
    private function deleteBestEffort(PersonalAccessToken $token): void
    {
        try {
            $token->delete();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
