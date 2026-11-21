<?php

namespace App\Services\McpLinks;

use App\DataObjects\McpLinks\ExchangedMcpLink;
use App\DataObjects\McpLinks\OpenedMcpLink;
use App\Enums\McpLinkReason;
use App\Models\ApiClient;
use App\Models\McpLink;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
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
     * @throws ValidationException
     * @throws TooManyOpenAttemptsException
     * @throws FirstAccessPendingException
     */
    public function open(ApiClient $client, string $email, string $password, string $mcpClient): OpenedMcpLink
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

        // Step 3: no link before the user has chosen the first password. Stricter than
        // isFirstAccessPending(), which still lets the user in while the choice is optional.
        if ($user->must_set_password) {
            throw new FirstAccessPendingException;
        }

        // Step 4: issue the credential, store its fingerprint only
        $credential = bin2hex(random_bytes((int) config('mcp-link.credential_bytes')));
        $link = McpLink::query()->create([
            'user_id' => $user->id,
            'api_client_id' => $client->id,
            'mcp_client' => $mcpClient,
            'credential_hash' => McpLink::fingerprint($credential),
            'password_fingerprint' => McpLink::fingerprint($user->password),
            'expires_at' => now()->addDays((int) config('mcp-link.link_ttl_days')),
        ]);

        return new OpenedMcpLink($link, $credential, $user);
    }

    /**
     * Atomic: the link row is locked while it is checked and the token is created
     * and attached, so a concurrent revocation or credential change either comes
     * first (the exchange is refused) or last (it deletes the new token), and a
     * failure midway leaves no token behind.
     *
     * @throws McpLinkRejectedException
     * @throws ReleaseCommitNotConfiguredException
     */
    public function exchange(ApiClient $client, string $credential): ExchangedMcpLink
    {
        // The contract returns the commit of QNET2 in service: without it no token is issued.
        $releaseCommit = config('mcp-link.release_commit');

        if (! filled($releaseCommit)) {
            throw new ReleaseCommitNotConfiguredException;
        }

        return DB::transaction(function () use ($client, $credential, $releaseCommit): ExchangedMcpLink {
            // Step 1: the link must be one of this client
            $link = McpLink::query()
                ->where('credential_hash', McpLink::fingerprint($credential))
                ->where('api_client_id', $client->id)
                ->lockForUpdate()
                ->first() ?? throw new McpLinkRejectedException(McpLinkReason::LinkUnknown);

            // Step 2: revoked, expired, user gone or inactive, password changed since opening
            $user = $link->user;

            match (true) {
                $link->revoked_at !== null => throw new McpLinkRejectedException(McpLinkReason::LinkRevoked),
                $link->expires_at->isPast() => throw new McpLinkRejectedException(McpLinkReason::LinkExpired),
                $user === null || ! $user->is_active => throw new McpLinkRejectedException(McpLinkReason::UserInactive),
                ! hash_equals($link->password_fingerprint, McpLink::fingerprint($user->password)) => throw new McpLinkRejectedException(McpLinkReason::PasswordChanged),
                default => null,
            };

            // Step 3: short-lived token, never beyond the link, bound to the connector client
            $expiresAt = now()->addMinutes((int) config('mcp-link.access_token_ttl_minutes'));
            $expiresAt = $expiresAt->greaterThan($link->expires_at) ? $link->expires_at->copy() : $expiresAt;

            $newToken = $user->createToken('mcp-link-'.$link->id, ['*'], $expiresAt);
            $newToken->accessToken->forceFill(['api_client_id' => $client->id])->save();
            $link->tokens()->attach($newToken->accessToken->id);

            return new ExchangedMcpLink($user, $newToken->plainTextToken, $expiresAt, $releaseCommit);
        });
    }

    public function issuedFor(PersonalAccessToken $token): bool
    {
        return DB::table('mcp_link_tokens')->where('personal_access_token_id', $token->getKey())->exists();
    }

    /**
     * Revoke a link of this client and delete the user tokens it issued.
     */
    public function revoke(ApiClient $client, int $linkId): void
    {
        $this->drop(McpLink::query()->where('api_client_id', $client->id)->findOrFail($linkId), McpLinkReason::RevokedByClient);
    }

    /**
     * Mark every live link of the user as fallen and delete the user tokens they issued.
     */
    public function dropForUser(User $user, McpLinkReason $reason): void
    {
        McpLink::query()->where('user_id', $user->id)->whereNull('revoked_at')->get()
            ->each(fn (McpLink $link) => $this->drop($link, $reason));
    }

    /**
     * Lock order, the same as the exchange: the link row first, then its tokens.
     * Both reads are locking (current) reads: under REPEATABLE READ a plain SELECT
     * would use the snapshot of an earlier read of the caller's transaction and
     * miss a token the exchange committed meanwhile.
     */
    private function drop(McpLink $link, McpLinkReason $reason): void
    {
        DB::transaction(function () use ($link, $reason): void {
            $locked = McpLink::query()->lockForUpdate()->find($link->id);

            if ($locked === null) {
                return;
            }

            $tokenIds = DB::table('mcp_link_tokens')->where('mcp_link_id', $locked->id)->lockForUpdate()->pluck('personal_access_token_id');

            PersonalAccessToken::query()->whereIn('id', $tokenIds)->delete();

            if ($locked->revoked_at === null) {
                $locked->forceFill(['revoked_at' => now(), 'revoked_reason' => $reason])->save();
            }
        });
    }
}
