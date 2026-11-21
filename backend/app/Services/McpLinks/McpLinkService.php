<?php

namespace App\Services\McpLinks;

use App\Models\ApiClient;
use App\Models\McpLink;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Opening, exchange and revocation of MCP links. The credential is returned
 * once; only its SHA-256 fingerprint is stored.
 */
class McpLinkService
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * @return array{link: McpLink, credential: string, user: User}
     *
     * @throws ValidationException
     * @throws TooManyOpenAttemptsException
     * @throws FirstAccessPendingException
     */
    public function open(ApiClient $client, string $email, string $password, string $mcpClient): array
    {
        // Step 1: own attempt limit, per account
        $limiterKey = 'mcp-link-open:'.Str::lower($email);

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('mcp-link.open_attempts.max'))) {
            throw new TooManyOpenAttemptsException(RateLimiter::availableIn($limiterKey));
        }

        // Step 2: same credential rules and generic failure as client-login
        try {
            $user = $this->auth->authenticate($email, $password, revealInactive: false);
        } catch (ValidationException $e) {
            RateLimiter::hit($limiterKey, (int) config('mcp-link.open_attempts.decay_minutes') * 60);

            throw $e;
        }

        RateLimiter::clear($limiterKey);

        // Step 3: no link before the user has chosen the first password
        if ($user->must_set_password) {
            throw new FirstAccessPendingException;
        }

        // Step 4: issue the credential, store its fingerprint only
        $credential = bin2hex(random_bytes(32));
        $link = McpLink::query()->create([
            'user_id' => $user->id,
            'api_client_id' => $client->id,
            'mcp_client' => $mcpClient,
            'credential_hash' => McpLink::fingerprint($credential),
            'password_fingerprint' => McpLink::fingerprint($user->password),
            'expires_at' => now()->addDays((int) config('mcp-link.link_ttl_days')),
        ]);

        return ['link' => $link, 'credential' => $credential, 'user' => $user];
    }

    /**
     * @return array{token: string, expires_at: Carbon, user: User}
     *
     * @throws McpLinkRejectedException
     */
    public function exchange(ApiClient $client, string $credential): array
    {
        // Step 1: the link must be one of this client
        $link = McpLink::query()
            ->where('credential_hash', McpLink::fingerprint($credential))
            ->where('api_client_id', $client->id)
            ->first() ?? throw new McpLinkRejectedException('link_unknown');

        // Step 2: revoked, expired, user gone or inactive, password changed since opening
        $user = $link->user;

        match (true) {
            $link->revoked_at !== null => throw new McpLinkRejectedException('link_revoked'),
            $link->expires_at->isPast() => throw new McpLinkRejectedException('link_expired'),
            $user === null || ! $user->is_active => throw new McpLinkRejectedException('user_inactive'),
            ! hash_equals($link->password_fingerprint, McpLink::fingerprint($user->password)) => throw new McpLinkRejectedException('password_changed'),
            default => null,
        };

        // Step 3: short-lived token, never beyond the link, bound to the connector client
        $expiresAt = now()->addMinutes((int) config('mcp-link.access_token_ttl_minutes'));
        $expiresAt = $expiresAt->greaterThan($link->expires_at) ? $link->expires_at->copy() : $expiresAt;

        $newToken = $user->createToken('mcp-link-'.$link->id, ['*'], $expiresAt);
        $newToken->accessToken->forceFill(['api_client_id' => $client->id])->save();
        $link->tokens()->attach($newToken->accessToken->id);

        return ['token' => $newToken->plainTextToken, 'expires_at' => $expiresAt, 'user' => $user];
    }

    /**
     * Revoke a link of this client and delete the user tokens it issued.
     */
    public function revoke(ApiClient $client, int $linkId): void
    {
        $this->drop(McpLink::query()->where('api_client_id', $client->id)->findOrFail($linkId), McpLink::REVOKED_BY_CLIENT);
    }

    /**
     * Mark every live link of the user as fallen and delete the user tokens they issued.
     */
    public function dropForUser(User $user, string $reason): void
    {
        McpLink::query()->where('user_id', $user->id)->whereNull('revoked_at')->get()
            ->each(fn (McpLink $link) => $this->drop($link, $reason));
    }

    private function drop(McpLink $link, string $reason): void
    {
        PersonalAccessToken::query()->whereIn('id', $link->tokens()->pluck('personal_access_tokens.id'))->delete();

        if ($link->revoked_at === null) {
            $link->forceFill(['revoked_at' => now(), 'revoked_reason' => $reason])->save();
        }
    }
}
