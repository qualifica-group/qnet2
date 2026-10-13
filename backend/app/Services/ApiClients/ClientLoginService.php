<?php

namespace App\Services\ApiClients;

use App\DataObjects\ApiClients\ClientLoginResult;
use App\Models\ApiClient;
use App\Services\AuthService;
use Illuminate\Validation\ValidationException;

/**
 * Login of a QNet user through an API client (spec 0210): the credentials are
 * checked like the app login, but the issued token is bound to the client
 * (`api_client_id`) and expires after config('external-api.user_tokens').
 */
class ClientLoginService
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * @throws ValidationException
     */
    public function handle(ApiClient $client, string $email, string $password, string $deviceName): ClientLoginResult
    {
        // Step 1: same credential, active and technical-user rules as the app login,
        // with one generic failure message
        $user = $this->auth->authenticate($email, $password, revealInactive: false);

        // Step 2: issue the client-bound token
        $expiresAt = now()->addMinutes((int) config('external-api.user_tokens.ttl_minutes'));
        $newToken = $user->createToken($deviceName, ['*'], $expiresAt);
        $newToken->accessToken->forceFill(['api_client_id' => $client->id])->save();

        return new ClientLoginResult(user: $user, token: $newToken->plainTextToken, expiresAt: $expiresAt);
    }
}
