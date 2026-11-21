<?php

namespace App\Http\Controllers\McpLinks;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\McpLinks\ExchangeMcpLinkRequest;
use App\Http\Requests\McpLinks\OpenMcpLinkRequest;
use App\Http\Requests\McpLinks\RevokeMcpLinkRequest;
use App\Models\User;
use App\Services\McpLinks\FirstAccessPendingException;
use App\Services\McpLinks\McpLinkRejectedException;
use App\Services\McpLinks\McpLinkService;
use App\Services\McpLinks\ReleaseCommitNotConfiguredException;
use App\Services\McpLinks\TooManyOpenAttemptsException;
use App\Support\ApiClients\ApiClientRequestContext;
use Illuminate\Http\JsonResponse;

/**
 * MCP link module: open, exchange, revoke (all three POST, so a client needs only
 * that method). Reached only with the API client key (EnsureApiClientKey on the
 * route group).
 */
class McpLinkController extends BaseApiController
{
    public function __construct(private readonly McpLinkService $service) {}

    public function open(OpenMcpLinkRequest $request): JsonResponse
    {
        $client = ApiClientRequestContext::resolve($request);
        $validated = $request->validated();

        try {
            $opened = $this->service->open($client, $validated['email'], $validated['password'], $validated['mcp_client']);
        } catch (TooManyOpenAttemptsException $e) {
            return $this->fail(__('Too many requests.'), HttpStatusEnum::TOO_MANY_REQUESTS->value)->withHeaders(['Retry-After' => $e->retryAfterSeconds]);
        } catch (FirstAccessPendingException) {
            return $this->fail(__('You must choose a new password before continuing.'), HttpStatusEnum::FORBIDDEN->value, data: ['reason' => 'first_access_pending']);
        }

        return $this->created([
            'link_id' => $opened->link->id,
            'credential' => $opened->credential,
            'expires_at' => $opened->link->expires_at->toIso8601String(),
            'user' => $this->userPayload($opened->user),
        ], 'Link opened.');
    }

    public function exchange(ExchangeMcpLinkRequest $request): JsonResponse
    {
        try {
            $exchanged = $this->service->exchange(ApiClientRequestContext::resolve($request), $request->validated('credential'));
        } catch (McpLinkRejectedException $e) {
            return $this->fail(__('The link is no longer valid.'), HttpStatusEnum::UNAUTHORIZED->value, data: ['reason' => $e->reason->value]);
        } catch (ReleaseCommitNotConfiguredException) {
            return $this->fail(__('The server is not configured to exchange links.'), HttpStatusEnum::INTERNAL_SERVER_ERROR->value, data: ['reason' => ReleaseCommitNotConfiguredException::REASON]);
        }

        return $this->ok([
            'token' => $exchanged->token,
            'token_type' => 'Bearer',
            'expires_at' => $exchanged->expiresAt->toIso8601String(),
            'release_commit' => $exchanged->releaseCommit,
            'user' => $this->userPayload($exchanged->user),
        ], 'Link exchanged.');
    }

    public function revoke(RevokeMcpLinkRequest $request, int $mcpLink): JsonResponse
    {
        $this->service->revoke(ApiClientRequestContext::resolve($request), $mcpLink);

        return $this->ok(null, 'Link revoked.');
    }

    /**
     * Identifier and name only: all the connector needs.
     *
     * @return array{id: int, name: string}
     */
    private function userPayload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name];
    }
}
