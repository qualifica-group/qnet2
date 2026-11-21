<?php

namespace App\Http\Controllers\McpLinks;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\McpLinks\ExchangeMcpLinkRequest;
use App\Http\Requests\McpLinks\OpenMcpLinkRequest;
use App\Services\McpLinks\FirstAccessPendingException;
use App\Services\McpLinks\McpLinkRejectedException;
use App\Services\McpLinks\McpLinkService;
use App\Services\McpLinks\TooManyOpenAttemptsException;
use App\Support\ApiClients\ApiClientRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MCP link module: open, exchange, revoke. Reached only with the API client key
 * (EnsureApiClientKey on the route group).
 */
class McpLinkController extends BaseApiController
{
    public function __construct(private readonly McpLinkService $service) {}

    public function open(OpenMcpLinkRequest $request): JsonResponse
    {
        $client = ApiClientRequestContext::resolve($request);
        $validated = $request->validated();

        try {
            $result = $this->service->open($client, $validated['email'], $validated['password'], $validated['mcp_client']);
        } catch (TooManyOpenAttemptsException $e) {
            return $this->fail(__('Too many requests.'), 429)->withHeaders(['Retry-After' => $e->retryAfterSeconds]);
        } catch (FirstAccessPendingException) {
            return $this->fail(__('You must choose a new password before continuing.'), HttpStatusEnum::FORBIDDEN->value, data: ['reason' => 'first_access_pending']);
        }

        return $this->created([
            'link_id' => $result['link']->id,
            'credential' => $result['credential'],
            'expires_at' => $result['link']->expires_at->toIso8601String(),
            'user' => ['id' => $result['user']->id, 'name' => $result['user']->name, 'email' => $result['user']->email],
        ], 'Link opened.');
    }

    public function exchange(ExchangeMcpLinkRequest $request): JsonResponse
    {
        try {
            $result = $this->service->exchange(ApiClientRequestContext::resolve($request), $request->validated('credential'));
        } catch (McpLinkRejectedException $e) {
            return $this->fail(__('The link is no longer valid.'), HttpStatusEnum::UNAUTHORIZED->value, data: ['reason' => $e->reason]);
        }

        return $this->ok([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
            'release_commit' => config('mcp-link.release_commit'),
            'user' => ['id' => $result['user']->id, 'name' => $result['user']->name, 'email' => $result['user']->email],
        ], 'Link exchanged.');
    }

    public function revoke(Request $request, int $mcpLink): JsonResponse
    {
        $this->service->revoke(ApiClientRequestContext::resolve($request), $mcpLink);

        return $this->ok(null, 'Link revoked.');
    }
}
