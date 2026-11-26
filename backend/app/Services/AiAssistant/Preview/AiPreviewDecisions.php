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
use App\Services\AiAssistant\Agent\AiTurnLink;
use App\Services\AiAssistant\Agent\ConnectorClient;
use App\Services\AiAssistant\Agent\ConnectorOutcomeUnknownException;
use App\Services\AiAssistant\Agent\ConnectorUnavailableException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * What ends the wait of a turn for its preview (spec 0433, D-3, D-17, D-18): the click on Confirm
 * or Cancel, the clock, or the fall of the link. Each one moves the turn from `awaiting_confirmation`
 * to `queued` (or closes it) with ONE guarded update, so only the first of two competing causes wins;
 * the connector is called only after the preview has been checked, and never more than once.
 *
 * A confirmation whose answer is lost after it was sent is `uncertain`, not "try again": the turn goes
 * on with that outcome and nothing is sent twice. A mark on the saved state, written before the call,
 * keeps that true even if the worker dies in the middle of it.
 */
final class AiPreviewDecisions
{
    /** Key of the mark, in the saved state of the turn, that a confirmation is in flight. */
    private const string CONFIRMING = 'confirming';

    public function __construct(
        private readonly AiTurnLink $link,
        private readonly ConnectorClient $connector,
    ) {}

    /**
     * @throws AiPreviewDecisionException
     * @throws ConnectorUnavailableException the connector did not act: the preview stays open
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

            [$outcome, $result] = $this->confirmWithConnector($current);
            $this->release($current, $preview, $outcome, $result);

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
                $this->releaseUnanswered($turn, $preview);
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

        if (AssistantLink::tokenIsUsable($this->link->session($turn))) {
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
            $this->releaseUnanswered($turn, $preview);

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
     * The connector confirms once. Its answer is the outcome; a lost answer is `uncertain`; a refusal
     * before it acted is thrown, and the preview stays open.
     *
     * @return array{0: AiPreviewOutcome, 1: string} the outcome and the result handed to the model
     *
     * @throws AiPreviewDecisionException the link of the person fell
     * @throws ConnectorUnavailableException
     */
    private function confirmWithConnector(AiTurn $turn): array
    {
        // A worker died with the call in flight: the write may exist, so the connector is not called again.
        if ($turn->resume_state[self::CONFIRMING] ?? false) {
            return $this->uncertain($turn);
        }

        try {
            $credential = $this->link->credential($turn);
            $this->markConfirming($turn, true);
            $answer = $this->connector->confirm($credential, (string) $turn->preview['id']);

            return [AiPreviewOutcome::fromEnvelope($answer['envelope']), $answer['text']];
        } catch (ConnectorOutcomeUnknownException) {
            return $this->uncertain($turn);
        } catch (Throwable $exception) {
            $this->markConfirming($turn, false);

            if (! AiTurnLink::isLost($exception)) {
                throw $exception;
            }

            $this->closeLinkLost($turn);

            throw new AiPreviewDecisionException(AiPreviewDecisionException::EXPIRED);
        }
    }

    /**
     * @return array{0: AiPreviewOutcome, 1: string}
     */
    private function uncertain(AiTurn $turn): array
    {
        return [AiPreviewOutcome::Uncertain, AiPreviewResults::uncertain($turn->locale)];
    }

    private function markConfirming(AiTurn $turn, bool $on): void
    {
        $state = $turn->resume_state ?? [];

        if ($on) {
            $state[self::CONFIRMING] = true;
        } else {
            unset($state[self::CONFIRMING]);
        }

        AiTurn::query()->whereKey($turn->id)->where('status', AiTurnStatus::AwaitingConfirmation)->update(['resume_state' => json_encode($state)]);
    }

    /**
     * No click gave the preview an answer (the clock, a late click): expired, unless a confirmation
     * was in flight that nobody saw the end of, which is uncertain.
     */
    private function releaseUnanswered(AiTurn $turn, AiPreview $preview): void
    {
        [$outcome, $result] = ($turn->resume_state[self::CONFIRMING] ?? false)
            ? $this->uncertain($turn)
            : [AiPreviewOutcome::Expired, AiPreviewResults::expired($turn->locale)];

        $this->release($turn, $preview, $outcome, $result);
    }

    /**
     * Hands the preview its result: the turn is queued again and the resume job is pushed, but only
     * if the turn still waits for this very preview. The outcome is saved on the preview now, so the
     * card shows it even if the turn can not resume.
     */
    private function release(AiTurn $turn, AiPreview $preview, AiPreviewOutcome $outcome, string $result): void
    {
        $state = $turn->resume_state ?? [];
        unset($state[self::CONFIRMING]);
        $state['pending'] = ['outcome' => $outcome->value, 'result' => $result];

        $released = AiTurn::query()->whereKey($turn->id)
            ->where('status', AiTurnStatus::AwaitingConfirmation)
            ->where('preview->id', $preview->id)
            ->where('preview->state', AiPreviewState::Open->value)
            ->update([
                'status' => AiTurnStatus::Queued,
                'preview' => json_encode($preview->settled($outcome)->withOutcome($outcome)->toArray()),
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
