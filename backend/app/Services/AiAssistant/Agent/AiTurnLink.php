<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Models\AiTurn;
use App\Services\McpLinks\AssistantLinkService;
use App\Services\McpLinks\AssistantSessionInvalidException;
use App\Services\McpLinks\FirstAccessPendingException;
use App\Services\McpLinks\McpLinkRejectedException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * The link of the person behind a turn (spec 0433, D-20): the session token the message came
 * from, and the credential opened with it. One place for the turn and for the preview decisions.
 */
final class AiTurnLink
{
    /** Every way the link of a person can be found fallen. */
    private const array LOST = [
        ConnectorLinkRejectedException::class,
        AssistantSessionInvalidException::class,
        McpLinkRejectedException::class,
        FirstAccessPendingException::class,
    ];

    public function __construct(private readonly AssistantLinkService $links) {}

    public static function isLost(Throwable $exception): bool
    {
        foreach (self::LOST as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function session(AiTurn $turn): ?PersonalAccessToken
    {
        return $turn->personal_access_token_id === null ? null : PersonalAccessToken::query()->find($turn->personal_access_token_id);
    }

    /**
     * @throws AssistantSessionInvalidException the session token is gone
     */
    public function credential(AiTurn $turn): string
    {
        $session = $this->session($turn) ?? throw new AssistantSessionInvalidException;

        return $this->links->credentialFor($turn->conversation->user, $session);
    }
}
