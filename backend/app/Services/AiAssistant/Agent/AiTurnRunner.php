<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiMessageRole;
use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Enums\AiTurnStepState;
use App\Models\AiTurn;
use App\Services\AiAssistant\Preview\AiPreviewResults;
use App\Services\AiAssistant\Preview\AiTurnSuspension;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use App\Services\McpLinks\AssistantClientNotConfiguredException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

/**
 * The loop of one agent turn (spec 0433, D-8, D-15, D-18): claim the turn, open the link of the
 * person, then alternate model rounds and connector tool calls until the model answers in text
 * or a cap stops it. Everything the browser shows is written on the turn row as it happens.
 * It never throws: every failure closes the turn with a fixed error code, and the person's
 * message is never touched (D-16).
 *
 * A turn that waited for a write and can not go on after the answer still tells the person what
 * became of the write (D-18): the outcome is a fixed message, and the saved state of the wait is
 * spent only when the turn closes.
 */
final class AiTurnRunner
{
    private const float TEXT_FLUSH_SECONDS = 0.3;

    private const string INVALID_ARGUMENTS_RESULT = '{"connettore":{"esito":"fallita","codice":"parametri_non_validi"},"dati":null}';

    public function __construct(
        private readonly AiTurnLink $link,
        private readonly ConnectorClient $connector,
        private readonly MeteredModelCall $model,
        private readonly AgentTurnMemory $memory,
        private readonly AiAssistantSettingsService $settings,
        private readonly AiTurnSuspension $suspension,
    ) {}

    public function run(int $turnId): void
    {
        // Step 1: claim the turn; a duplicate job finds it already taken and does nothing (D-18)
        $claimed = AiTurn::query()->whereKey($turnId)->where('status', AiTurnStatus::Queued)
            ->update(['status' => AiTurnStatus::Running, 'started_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $run = new AgentRun(AiTurn::query()->with(['conversation.user', 'userMessage'])->findOrFail($turnId));

        try {
            $this->execute($run);
        } catch (Throwable $exception) {
            [$status, $code] = $this->failureOf($exception);

            if ($code === AiTurnErrorCode::Internal) {
                Log::error('AI agent turn crashed', ['exception' => $exception::class, 'turn_id' => $turnId]);
            }

            $this->close($run, $status, $code);
        }
    }

    /**
     * Closes a turn the queue gave up on (job crash, timeout): same close as a failure of the loop.
     */
    public function fail(int $turnId, AiTurnErrorCode $code): void
    {
        $turn = AiTurn::query()->with('conversation')->find($turnId);

        if ($turn === null) {
            return;
        }

        $note = $this->suspension->unreportedOutcome($turn);

        DB::transaction(function () use ($turn, $code, $note): void {
            $closed = AiTurn::query()->whereKey($turn->id)->whereIn('status', AiTurnStatus::activeValues())->update([
                'status' => AiTurnStatus::Failed,
                'error_code' => $code,
                'resume_state' => null,
                'finished_at' => now(),
            ] + ($note === null ? [] : ['assistant_text' => $note]));

            if ($closed === 1 && $note !== null) {
                $this->saveAnswer($turn, $note, null);
            }
        });
    }

    private function execute(AgentRun $run): void
    {
        // Step 2: what the turn saved before it waited, so its counters and steps survive any failure below
        $resumed = $this->suspension->resume($run);

        // Step 3: model, routing and instructions in force; a switched-off assistant makes no call
        $settings = $this->settings->effective();

        if (! $settings->enabled) {
            throw new AgentTurnLimitException(AiTurnErrorCode::SwitchedOff);
        }

        // Step 4: the credential of the person's link, the tools and the first messages
        $credential = $this->link->credential($run->turn);
        $tools = $this->connector->tools($credential);
        $messages = $resumed ?? $this->memory->messages($run, $settings, $tools);

        // Step 5: model rounds until a text answer
        $this->converse($run, $settings, $credential, $messages, $tools);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    private function converse(AgentRun $run, AiEffectiveSettings $settings, string $credential, array $messages, array $tools): void
    {
        while (true) {
            $this->assertCanCallModel($run);

            $result = $this->modelRound($run, $settings, $messages, $tools);

            if ($run->aborted) {
                return;
            }

            if ($result->toolCalls === []) {
                $this->complete($run, $settings, $result);

                return;
            }

            // Step 6: tool calls; the assistant message goes back with its reasoning blocks unchanged
            $messages[] = $this->assistantMessage($result);

            if (! $this->runToolCalls($run, $credential, $result->toolCalls, $messages)) {
                return;
            }
        }
    }

    /**
     * Runs the calls of one round in order and adds each result to the messages.
     *
     * @param  list<array{id: string, name: string, arguments: string}>  $calls
     * @param  list<array<string, mixed>>  $messages
     * @return bool false when the turn must stop: it waits for a confirmation or was closed elsewhere
     */
    private function runToolCalls(AgentRun $run, string $credential, array $calls, array &$messages): bool
    {
        foreach ($calls as $position => $call) {
            $this->assertCanCallTool($run);
            $run->toolCalls++;

            $content = $call['name'] === AssistantTools::PREPARE_WRITE
                ? $this->prepareWrite($run, $credential, $call, $messages, array_column(array_slice($calls, $position + 1), 'id'))
                : $this->runTool($run, $credential, $call);

            if ($content === null) {
                return false;
            }

            $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => $content];
        }

        return ! $run->aborted;
    }

    /**
     * `prepare_write` under the lock of the link: check, call and wait are one step, so two writes of the
     * same link can not both pass the check before either has saved its wait (D-3). A valid preview saves
     * the turn and waits for the button, nothing stays running (D-17).
     *
     * @param  array{id: string, name: string, arguments: string}  $call
     * @param  list<array<string, mixed>>  $messages  up to the call, its result excluded
     * @param  list<string>  $laterCallIds  later calls of the same round, left out
     * @return string|null the result for the model, or null when the turn is now waiting (or was closed elsewhere)
     */
    private function prepareWrite(AgentRun $run, string $credential, array $call, array $messages, array $laterCallIds): ?string
    {
        $lock = $this->suspension->linkLock($run->turn);

        if (! $lock->get()) {
            return AiPreviewResults::busy($run->turn->locale);
        }

        try {
            if ($this->suspension->anotherPreviewIsOpen($run->turn)) {
                return AiPreviewResults::busy($run->turn->locale);
            }

            $issuedAt = CarbonImmutable::now();
            $content = $this->runTool($run, $credential, $call);
            $preview = $this->suspension->previewFrom($call['name'], $content, $issuedAt);

            if ($preview === null) {
                return $content;
            }

            $this->suspension->suspend($run, $messages, $preview, $call['id'], $laterCallIds);

            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * What ends a turn that threw, as the status and the fixed code it closes with.
     *
     * @return array{0: AiTurnStatus, 1: AiTurnErrorCode}
     */
    private function failureOf(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof AgentModelException, $exception instanceof AgentTurnLimitException => [
                in_array($exception->errorCode, [AiTurnErrorCode::LimitPerson, AiTurnErrorCode::LimitTotal], true) ? AiTurnStatus::LimitReached : AiTurnStatus::Failed,
                $exception->errorCode,
            ],
            AiTurnLink::isLost($exception) => [AiTurnStatus::Cancelled, AiTurnErrorCode::LinkLost],
            $exception instanceof ConnectorUnavailableException, $exception instanceof AssistantClientNotConfiguredException => [AiTurnStatus::Failed, AiTurnErrorCode::ConnectorUnavailable],
            default => [AiTurnStatus::Failed, AiTurnErrorCode::Internal],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    private function modelRound(AgentRun $run, AiEffectiveSettings $settings, array $messages, array $tools): AgentModelResult
    {
        $run->modelCalls++;
        $run->text = '';

        $result = $this->model->call($run, $settings, $messages, $tools, fn (string $fragment) => $this->streamText($run, $fragment));

        $run->model = $result->model ?? $run->model;

        // The preamble of a tool round is not the answer: the text shown restarts empty.
        if ($result->toolCalls !== []) {
            $run->text = '';
            $this->save($run, ['assistant_text' => '']);
        }

        return $result;
    }

    private function streamText(AgentRun $run, string $fragment): void
    {
        $run->text .= $fragment;

        if (microtime(true) - $run->lastFlushAt >= self::TEXT_FLUSH_SECONDS) {
            $run->lastFlushAt = microtime(true);
            $this->save($run, ['assistant_text' => $run->text]);
        }
    }

    /**
     * @param  array{id: string, name: string, arguments: string}  $call
     */
    private function runTool(AgentRun $run, string $credential, array $call): string
    {
        // Closed list: anything else never reaches the connector and leaves no step.
        if (! AssistantTools::isAllowed($call['name'])) {
            Log::warning('AI agent discarded a tool call that is not allowed');

            return ConnectorClient::REJECTED_RESULT;
        }

        $arguments = $this->decodeArguments($call['arguments']);

        if ($arguments === null) {
            return self::INVALID_ARGUMENTS_RESULT;
        }

        $index = $this->openStep($run, $call['name'], AssistantTools::operation($arguments));

        try {
            $result = $this->connector->call($credential, $call['name'], $arguments);
        } catch (ConnectorUnavailableException|ConnectorLinkRejectedException $exception) {
            $this->closeStep($run, $index, AiTurnStepState::Failed);

            throw $exception;
        }

        $this->closeStep($run, $index, $result['is_error'] ? AiTurnStepState::Failed : AiTurnStepState::Done);

        return $result['text'];
    }

    /**
     * Decoded as objects so an empty `{}` of the model stays an object when sent on; null when it is not one.
     */
    private function decodeArguments(string $json): ?stdClass
    {
        $arguments = $json === '' ? new stdClass : json_decode($json);

        if (is_array($arguments) && $arguments === []) {
            $arguments = new stdClass;
        }

        return $arguments instanceof stdClass ? $arguments : null;
    }

    private function complete(AgentRun $run, AiEffectiveSettings $settings, AgentModelResult $result): void
    {
        $text = trim($result->text);

        if ($text === '') {
            throw new AgentModelException(AiTurnErrorCode::StreamError);
        }

        $turn = $run->turn;

        // One transaction: the turn leaves `running` only together with its stored answer, and the
        // saved state of the wait is spent with it.
        DB::transaction(function () use ($run, $settings, $turn, $text): void {
            $closed = AiTurn::query()->whereKey($turn->id)->where('status', AiTurnStatus::Running)->update([
                'status' => AiTurnStatus::Completed,
                'assistant_text' => $text,
                'usage' => json_encode($run->usageForStorage()),
                'resume_state' => null,
                'finished_at' => now(),
            ]);

            // Cancelled while the model was answering: nothing to keep.
            if ($closed === 1) {
                $this->saveAnswer($turn, $text, $run->model ?? $settings->modelId, $run->inputTokens, $run->outputTokens);
            }
        });
    }

    /**
     * Stores the answer of the turn as a message of the conversation and links the turn to it.
     */
    private function saveAnswer(AiTurn $turn, string $text, ?string $model, ?int $inputTokens = null, ?int $outputTokens = null): void
    {
        $message = $turn->conversation->messages()->create([
            'role' => AiMessageRole::Assistant,
            'content' => $text,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);

        AiTurn::query()->whereKey($turn->id)->update(['assistant_message_id' => $message->id]);
        $turn->conversation->update(['last_message_at' => now()]);
    }

    private function assertCanCallModel(AgentRun $run): void
    {
        $this->assertWithinTime($run);

        if ($run->modelCalls >= (int) config('ai-assistant.agent.max_model_calls')) {
            throw new AgentTurnLimitException(AiTurnErrorCode::TooManySteps);
        }
    }

    private function assertCanCallTool(AgentRun $run): void
    {
        $this->assertWithinTime($run);

        if ($run->toolCalls >= (int) config('ai-assistant.agent.max_tool_calls')) {
            throw new AgentTurnLimitException(AiTurnErrorCode::TooManySteps);
        }
    }

    private function assertWithinTime(AgentRun $run): void
    {
        if ($run->elapsedSeconds() > (int) config('ai-assistant.agent.max_turn_seconds')) {
            throw new AgentTurnLimitException(AiTurnErrorCode::TurnTimeout);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantMessage(AgentModelResult $result): array
    {
        $message = [
            'role' => 'assistant',
            'content' => $result->text === '' ? null : $result->text,
            'tool_calls' => array_map(static fn (array $call): array => [
                'id' => $call['id'],
                'type' => 'function',
                'function' => ['name' => $call['name'], 'arguments' => $call['arguments']],
            ], $result->toolCalls),
        ];

        if ($result->reasoningDetails !== []) {
            $message['reasoning_details'] = $result->reasoningDetails;
        } elseif ($result->reasoning !== null) {
            $message['reasoning'] = $result->reasoning;
        }

        return $message;
    }

    private function openStep(AgentRun $run, string $tool, ?string $operation): int
    {
        $index = count($run->steps);
        $run->steps[] = [
            'index' => $index,
            'tool' => $tool,
            'label' => AssistantTools::label($tool, $run->turn->locale),
            'state' => AiTurnStepState::Running->value,
            'operation' => $operation,
        ];
        $this->save($run, ['steps' => $run->steps]);

        return $index;
    }

    private function closeStep(AgentRun $run, int $index, AiTurnStepState $state): void
    {
        $run->steps[$index]['state'] = $state->value;
        $this->save($run, ['steps' => $run->steps]);
    }

    /**
     * Closes the turn with a status and a fixed code; when the turn had a write outcome nobody has
     * been told yet, the person gets it as the fixed message of the assistant (D-18).
     */
    private function close(AgentRun $run, AiTurnStatus $status, AiTurnErrorCode $code): void
    {
        foreach ($run->steps as $index => $step) {
            if ($step['state'] === AiTurnStepState::Running->value) {
                $run->steps[$index]['state'] = AiTurnStepState::Failed->value;
            }
        }

        $note = $this->suspension->unreportedOutcome($run->turn);

        DB::transaction(function () use ($run, $status, $code, $note): void {
            $closed = AiTurn::query()->whereKey($run->turn->id)->whereIn('status', AiTurnStatus::activeValues())->update([
                'status' => $status,
                'error_code' => $code,
                'assistant_text' => $note ?? ($run->text === '' ? null : $run->text),
                'steps' => json_encode($run->steps),
                'usage' => json_encode($run->usageForStorage()),
                'resume_state' => null,
                'finished_at' => now(),
            ]);

            if ($closed === 1 && $note !== null) {
                $this->saveAnswer($run->turn, $note, $run->model, $run->inputTokens, $run->outputTokens);
            }
        });
    }

    /**
     * Saves while the turn is still running; a turn closed elsewhere (conversation deleted,
     * link fallen) stops the run at the next check.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function save(AgentRun $run, array $attributes): void
    {
        $attributes = array_map(static fn (mixed $value): mixed => is_array($value) ? json_encode($value) : $value, $attributes);

        $saved = AiTurn::query()->whereKey($run->turn->id)->where('status', AiTurnStatus::Running)->update($attributes);

        if ($saved === 0) {
            $run->aborted = true;
        }
    }
}
