<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Preview;

use App\Enums\AiPreviewOutcome;
use App\Enums\AiPreviewState;
use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\RunAiTurn;
use App\Models\AiTurn;
use App\Models\AssistantLink;
use App\Services\AiAssistant\Agent\ConnectorClient;
use App\Services\AiAssistant\Agent\ConnectorLinkRejectedException;
use App\Services\AiAssistant\Agent\ConnectorUnavailableException;
use App\Services\McpLinks\AssistantLinkService;
use App\Services\McpLinks\AssistantSessionInvalidException;
use App\Services\McpLinks\FirstAccessPendingException;
use App\Services\McpLinks\McpLinkRejectedException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * What ends the wait of a turn for its preview (spec 0433, D-3, D-17, D-18): the click on Confirm
 * or Cancel, the clock, or the fall of the link. Each one moves the turn from `awaiting_confirmation`
 * to `queued` (or closes it) with ONE guarded update, so only the first of two competing causes wins;
 * the connector is called only after the preview has been checked, and never more than once.
 */
final class AiPreviewDecisions
{
    public function __construct(
        private readonly AssistantLinkService $links,
        private readonly ConnectorClient $connector,
    ) {}

    /**
     * @throws AiPreviewDecisionException
     * @throws ConnectorUnavailableException the preview stays open
     */
    public function confirm(AiTurn $turn, string $previewId): AiTurn
    {
        // Step 1: the preview must be the open one of this turn, before any call
        $preview = $this->assertOpen($turn, $previewId);

        if (! $preview->confirmable) {
            throw new AiPreviewDecisionException(AiPreviewDecisionException::NOT_CONFIRMABLE);
        }

        // Step 2: one decision at a time, then the connector confirms and the turn goes on with its answer
        return $this->exclusively($turn, function () use ($turn, $previewId): AiTurn {
            $current = $turn->fresh();
            $preview = $this->assertOpen($current, $previewId);

            $answer = $this->confirmWithConnector($current);
            $this->release($current, $preview, AiPreviewOutcome::fromEnvelope($answer['envelope']), $answer['text']);

            return $current->fresh();
        });
    }

    /**
     * @throws AiPreviewDecisionException
     */
    public function cancel(AiTurn $turn, string $previewId): AiTurn
    {
        $this->assertOpen($turn, $previewId);

        return $this->exclusively($turn, function () use ($turn, $previewId): AiTurn {
            $current = $turn->fresh();
            $preview = $this->assertOpen($current, $previewId);

            $this->release($current, $preview, AiPreviewOutcome::Cancelled, AiPreviewResults::cancelled($current->locale));

            return $current->fresh();
        });
    }

    /**
     * The delayed job of the expiry: acts only if the turn still waits for THIS preview.
     */
    public function expire(int $turnId, string $previewId): void
    {
        $lock = Cache::lock($this->lockName($turnId), $this->lockSeconds());

        try {
            // A confirmation in progress decides first: the job waits for it, then finds the turn moved.
            $lock->block($this->lockSeconds());
        } catch (LockTimeoutException) {
            return;
        }

        try {
            $turn = AiTurn::query()->find($turnId);
            $preview = $turn?->preview === null ? null : AiPreview::fromArray($turn->preview);

            if ($turn === null || $turn->status !== AiTurnStatus::AwaitingConfirmation || $preview?->id !== $previewId || ! $preview->isOpen()) {
                return;
            }

            if (! $this->closeIfLinkLost($turn)) {
                $this->release($turn, $preview, AiPreviewOutcome::Expired, AiPreviewResults::expired($turn->locale));
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * A turn waiting while its session token is gone (renewed, logged out, expired): the link has
     * fallen, the preview arrives as expired and the turn closes cancelled (AC-009, D-20).
     */
    public function closeIfLinkLost(AiTurn $turn): bool
    {
        if ($turn->status !== AiTurnStatus::AwaitingConfirmation) {
            return false;
        }

        $session = $turn->personal_access_token_id === null ? null : PersonalAccessToken::query()->find($turn->personal_access_token_id);

        if (AssistantLink::tokenIsUsable($session)) {
            return false;
        }

        $this->closeLinkLost($turn);

        return true;
    }

    /**
     * @return AiPreview the open preview
     *
     * @throws AiPreviewDecisionException
     */
    private function assertOpen(AiTurn $turn, string $previewId): AiPreview
    {
        if (! is_array($turn->preview) || ($turn->preview['id'] ?? null) !== $previewId) {
            throw new AiPreviewDecisionException(AiPreviewDecisionException::NOT_FOUND);
        }

        $preview = AiPreview::fromArray($turn->preview);

        if ($turn->status !== AiTurnStatus::AwaitingConfirmation || ! $preview->isOpen()) {
            throw new AiPreviewDecisionException($this->closedReason($preview));
        }

        if ($this->closeIfLinkLost($turn)) {
            throw new AiPreviewDecisionException(AiPreviewDecisionException::EXPIRED);
        }

        if ($preview->isPast()) {
            $this->release($turn, $preview, AiPreviewOutcome::Expired, AiPreviewResults::expired($turn->locale));

            throw new AiPreviewDecisionException(AiPreviewDecisionException::EXPIRED);
        }

        return $preview;
    }

    private function closedReason(AiPreview $preview): string
    {
        return match (true) {
            $preview->state === AiPreviewState::Expired => AiPreviewDecisionException::EXPIRED,
            $preview->isOpen() => AiPreviewDecisionException::NOT_OPEN,
            default => AiPreviewDecisionException::ALREADY_USED,
        };
    }

    /**
     * @return array{text: string, envelope: array<string, mixed>}
     *
     * @throws AiPreviewDecisionException the link of the person fell
     */
    private function confirmWithConnector(AiTurn $turn): array
    {
        $session = PersonalAccessToken::query()->find($turn->personal_access_token_id);

        try {
            $credential = $session === null
                ? throw new AssistantSessionInvalidException
                : $this->links->credentialFor($turn->conversation->user, $session);

            return $this->connector->confirm($credential, (string) $turn->preview['id']);
        } catch (ConnectorLinkRejectedException|AssistantSessionInvalidException|McpLinkRejectedException|FirstAccessPendingException) {
            $this->closeLinkLost($turn);

            throw new AiPreviewDecisionException(AiPreviewDecisionException::EXPIRED);
        }
    }

    /**
     * Hands the preview its result: the turn is queued again and the resume job is pushed, but only
     * if the turn still waits for this very preview.
     */
    private function release(AiTurn $turn, AiPreview $preview, AiPreviewOutcome $outcome, string $result): void
    {
        $state = $turn->resume_state ?? [];
        $state['pending'] = ['outcome' => $outcome->value, 'result' => $result];

        $released = AiTurn::query()->whereKey($turn->id)
            ->where('status', AiTurnStatus::AwaitingConfirmation)
            ->where('preview->id', $preview->id)
            ->where('preview->state', AiPreviewState::Open->value)
            ->update([
                'status' => AiTurnStatus::Queued,
                'preview' => json_encode($preview->settled($outcome)->toArray()),
                'resume_state' => json_encode($state),
            ]);

        if ($released === 1) {
            RunAiTurn::dispatch($turn->id);
        }
    }

    private function closeLinkLost(AiTurn $turn): void
    {
        $preview = AiPreview::fromArray($turn->preview)->settled(AiPreviewOutcome::Expired)->withOutcome(AiPreviewOutcome::Expired);

        AiTurn::query()->whereKey($turn->id)->where('status', AiTurnStatus::AwaitingConfirmation)->update([
            'status' => AiTurnStatus::Cancelled,
            'error_code' => AiTurnErrorCode::LinkLost,
            'preview' => json_encode($preview->toArray()),
            'resume_state' => null,
            'finished_at' => now(),
        ]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $decision
     * @return T
     *
     * @throws AiPreviewDecisionException another decision is in progress
     */
    private function exclusively(AiTurn $turn, callable $decision): mixed
    {
        $lock = Cache::lock($this->lockName($turn->id), $this->lockSeconds());

        if (! $lock->get()) {
            throw new AiPreviewDecisionException(AiPreviewDecisionException::ALREADY_USED);
        }

        try {
            return $decision();
        } finally {
            $lock->release();
        }
    }

    private function lockName(int $turnId): string
    {
        return "ai-assistant:preview-decision:{$turnId}";
    }

    /** The longest the lock holder can take: the connector call plus a margin. */
    private function lockSeconds(): int
    {
        return (int) config('ai-assistant.connector.timeout_seconds') + 30;
    }
}
