<?php

namespace App\Http\Controllers\ApiClients;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ApiClients\ClientLoginRequest;
use App\Services\ApiClients\ClientLoginService;
use App\Support\ApiClients\ApiClientRequestContext;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/auth/client-login (spec 0210): an external system, authenticated
 * with its client key, signs a QNet user in and acts with that user's permissions.
 */
class ClientLoginController extends BaseApiController
{
    public function __construct(private readonly ClientLoginService $service) {}

    public function __invoke(ClientLoginRequest $request): JsonResponse
    {
        // Only the client key may call this: not an app token, not a client-login token.
        $client = ApiClientRequestContext::resolve($request);

        if ($client === null || ! $request->user()->isServiceAccount()) {
            return $this->fail(__('This endpoint requires an API client key.'), HttpStatusEnum::FORBIDDEN->value);
        }

        $validated = $request->validated();

        $result = $this->service->handle($client, $validated['email'], $validated['password'], $request->deviceName());

        return $this->ok([
            'token' => $result->token,
            'token_type' => 'Bearer',
            'expires_at' => $result->expiresAt->toIso8601String(),
            'user' => [
                'id' => $result->user->id,
                'name' => $result->user->name,
                'email' => $result->user->email,
            ],
        ], 'Authenticated.');
    }
}
