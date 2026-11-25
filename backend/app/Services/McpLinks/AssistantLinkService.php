<?php

namespace App\Services\McpLinks;

use App\Enums\McpLinkReason;
use App\Models\ApiClient;
use App\Models\AssistantLink;
use App\Models\McpLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Link of the AI assistant, opened from the session of a person already in QNET2
 * (ADR 0020): internal call, no route. The link is bound to the person's Sanctum
 * token and falls with it; the credential, returned in clear once on opening, is
 * kept encrypted for the next turns of the same token.
 *
 * Lock order is the one of McpLinkService: the user row first.
 */
class AssistantLinkService
{
    public function __construct(private readonly McpLinkService $links) {}

    /**
     * The credential of the live assistant link of this session token: the stored one, or
     * a new link when there is none or the previous one has fallen.
     *
     * @throws AssistantClientNotConfiguredException
     * @throws AssistantSessionInvalidException
     * @throws McpLinkRejectedException
     * @throws FirstAccessPendingException
     */
    public function credentialFor(User $user, PersonalAccessToken $session): string
    {
        // Step 1: where assistant links belong
        $client = $this->connectorClient();

        return DB::transaction(function () use ($client, $user, $session): string {
            // Step 2: re-check on the locked rows
            $locked = User::query()->lockForUpdate()->find($user->id);
            $this->assertUserCanLink($locked);
            $this->assertLiveSession($locked, $session);

            // Step 3: links of this person that fell leave no credential behind
            $this->retireFallen($locked);

            // Step 4: the live link of this token, else a new one
            $existing = AssistantLink::query()->where('personal_access_token_id', $session->id)->first();

            return $existing?->credential ?? $this->open($client, $locked, $session);
        });
    }

    private function open(ApiClient $client, User $user, PersonalAccessToken $session): string
    {
        $opened = $this->links->issueLink($client, $user, (string) config('mcp-link.assistant_mcp_client'));

        AssistantLink::query()->create([
            'user_id' => $user->id,
            'mcp_link_id' => $opened->link->id,
            'personal_access_token_id' => $session->id,
            'credential' => $opened->credential,
        ]);

        return $opened->credential;
    }

    private function connectorClient(): ApiClient
    {
        $id = config('mcp-link.assistant_client_id');
        $client = filled($id) ? ApiClient::query()->find($id) : null;

        return $client?->is_active ? $client : throw new AssistantClientNotConfiguredException;
    }

    private function assertUserCanLink(?User $user): void
    {
        if ($user === null || ! $user->is_active || $user->isServiceAccount()) {
            throw new McpLinkRejectedException(McpLinkReason::UserInactive);
        }

        // Same rule as the opening from the login page.
        if ($user->must_set_password) {
            throw new FirstAccessPendingException;
        }
    }

    /**
     * A token of the user's own sessions: not one of a link, not one of an API client, not expired.
     */
    private function assertLiveSession(User $user, PersonalAccessToken $session): void
    {
        $token = PersonalAccessToken::query()->find($session->id);

        $valid = $token !== null
            && $token->tokenable_type === $user->getMorphClass()
            && (int) $token->tokenable_id === $user->id
            && $token->api_client_id === null
            && McpLink::linkIdFromAbilities($token->abilities ?? []) === null
            && AssistantLink::tokenIsUsable($token);

        if (! $valid) {
            throw new AssistantSessionInvalidException;
        }
    }

    /**
     * Drops the links whose session ended or that fell for another cause (the mark is already
     * there for those, so the reason only fills a link that fell without one), and their rows.
     */
    private function retireFallen(User $user): void
    {
        AssistantLink::query()->where('user_id', $user->id)->get()->each(function (AssistantLink $row) use ($user): void {
            $link = McpLink::query()->find($row->mcp_link_id);

            if ($link !== null && $row->sessionIsAlive() && $link->isAliveFor($user)) {
                return;
            }

            if ($link !== null) {
                $this->links->drop($link, McpLinkReason::SessionEnded);
            }

            $row->delete();
        });
    }
}
