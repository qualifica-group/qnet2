<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Preview;

use App\Enums\AiPreviewOutcome;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\ExpireAiPreview;
use App\Models\AiTurn;
use App\Services\AiAssistant\Agent\AgentRun;
use App\Services\AiAssistant\Agent\AssistantTools;
use Carbon\CarbonImmutable;

/**
 * The two ends of the wait for a confirmation (spec 0433, D-17): saving the turn before it goes to
 * `awaiting_confirmation`, so that no job stays in execution, and taking it up again when the
 * person, the clock or the lost link has given the preview its result (D-18).
 */
final class AiTurnSuspension
{
    /**
     * The preview of a `prepare_write` result; expiry runs from the instant the call was SENT, so it
     * is always before the connector one (the connector writes the preview after receiving the call).
     */
    public function previewFrom(string $tool, string $resultText, CarbonImmutable $issuedAt): ?AiPreview
    {
        if ($tool !== AssistantTools::PREPARE_WRITE) {
            return null;
        }

        $lifeSeconds = (int) config('ai-assistant.agent.preview_ttl_seconds') - (int) config('ai-assistant.agent.preview_margin_seconds');

        return AiPreview::fromToolResult($resultText, $issuedAt->addSeconds($lifeSeconds));
    }

    /**
     * One preview open at a time for a link (one session token): another turn of the same session
     * already waits for its own. The connector would silently replace the first one.
     */
    public function anotherPreviewIsOpen(AiTurn $turn): bool
    {
        if ($turn->personal_access_token_id === null) {
            return false;
        }

        return AiTurn::query()
            ->where('personal_access_token_id', $turn->personal_access_token_id)
            ->where('status', AiTurnStatus::AwaitingConfirmation)
            ->whereKeyNot($turn->id)
            ->get()
            ->map(fn (AiTurn $other): AiPreview => AiPreview::fromArray($other->preview))
            ->contains(fn (AiPreview $preview): bool => $preview->isOpen() && ! $preview->isPast());
    }

    /**
     * Saves what the turn needs, then moves it to `awaiting_confirmation` and schedules the expiry.
     * `$messages` stop BEFORE the result of the `prepare_write` call: its result is the outcome.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<string>  $skippedCallIds  later calls of the same round, not run
     */
    public function suspend(AgentRun $run, array $messages, AiPreview $preview, string $callId, array $skippedCallIds): bool
    {
        $state = [
            'messages' => $messages,
            'preview_call_id' => $callId,
            'skipped_call_ids' => $skippedCallIds,
            'model_calls' => $run->modelCalls,
            'tool_calls' => $run->toolCalls,
            'input_tokens' => $run->inputTokens,
            'output_tokens' => $run->outputTokens,
            'cached_tokens' => $run->cachedTokens,
            'reasoning_tokens' => $run->reasoningTokens,
            'cost_usd' => $run->costUsd,
            'model' => $run->model,
            'elapsed_seconds' => $run->elapsedSeconds(),
            'pending' => null,
        ];

        $saved = AiTurn::query()->whereKey($run->turn->id)->where('status', AiTurnStatus::Running)->update([
            'status' => AiTurnStatus::AwaitingConfirmation,
            'assistant_text' => null,
            'preview' => json_encode($preview->toArray()),
            'resume_state' => json_encode($state),
            'usage' => json_encode($run->usageForStorage()),
        ]);

        if ($saved === 0) {
            $run->aborted = true;

            return false;
        }

        ExpireAiPreview::dispatch($run->turn->id, $preview->id)->delay($preview->expiresAt);

        return true;
    }

    /**
     * The messages of a turn that was waiting, with the outcome as the result of its `prepare_write`
     * call, or null when the turn starts from the beginning. The saved state is spent: it is cleared.
     *
     * @return list<array<string, mixed>>|null
     */
    public function resume(AgentRun $run): ?array
    {
        $turn = $run->turn;
        $state = $turn->resume_state;

        if (! is_array($state) || ! is_array($state['pending'] ?? null)) {
            return null;
        }

        $run->modelCalls = (int) $state['model_calls'];
        $run->toolCalls = (int) $state['tool_calls'];
        $run->inputTokens = (int) $state['input_tokens'];
        $run->outputTokens = (int) $state['output_tokens'];
        $run->cachedTokens = (int) $state['cached_tokens'];
        $run->reasoningTokens = (int) $state['reasoning_tokens'];
        $run->costUsd = (string) $state['cost_usd'];
        $run->model = $state['model'];
        $run->carriedSeconds = (float) $state['elapsed_seconds'];
        $run->steps = $turn->steps ?? [];

        $messages = $state['messages'];
        $messages[] = ['role' => 'tool', 'tool_call_id' => $state['preview_call_id'], 'content' => $state['pending']['result']];

        foreach ($state['skipped_call_ids'] as $skippedId) {
            $messages[] = ['role' => 'tool', 'tool_call_id' => $skippedId, 'content' => AiPreviewResults::skipped()];
        }

        $preview = AiPreview::fromArray($turn->preview)->withOutcome(AiPreviewOutcome::from($state['pending']['outcome']));
        AiTurn::query()->whereKey($turn->id)->where('status', AiTurnStatus::Running)->update([
            'preview' => json_encode($preview->toArray()),
            'resume_state' => null,
        ]);

        return $messages;
    }
}
