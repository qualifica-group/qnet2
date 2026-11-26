<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiMessageRole;
use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Models\AiTurn;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use App\Services\McpLinks\AssistantClientNotConfiguredException;
use App\Services\McpLinks\AssistantLinkService;
use App\Services\McpLinks\AssistantSessionInvalidException;
use App\Services\McpLinks\FirstAccessPendingException;
use App\Services\McpLinks\McpLinkRejectedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use stdClass;
use Throwable;

/**
 * The loop of one agent turn (spec 0433, D-8, D-15, D-18): claim the turn, open the link of the
 * person, then alternate model rounds and connector tool calls until the model answers in text
 * or a cap stops it. Everything the browser shows is written on the turn row as it happens.
 * It never throws: every failure closes the turn with a fixed error code, and the person's
 * message is never touched (D-16).
 */
final class AiTurnRunner
{
    private const float TEXT_FLUSH_SECONDS = 0.3;

    private const string INVALID_ARGUMENTS_RESULT = '{"connettore":{"esito":"fallita","codice":"parametri_non_validi"},"dati":null}';

    public function __construct(
        private readonly AssistantLinkService $links,
        private readonly ConnectorClient $connector,
        private readonly MeteredModelCall $model,
        private readonly AgentTurnMemory $memory,
        private readonly AiAssistantSettingsService $settings,
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
        } catch (AgentModelException|AgentTurnLimitException $exception) {
            $limit = in_array($exception->errorCode, [AiTurnErrorCode::LimitPerson, AiTurnErrorCode::LimitTotal], true);

            $this->close($run, $limit ? AiTurnStatus::LimitReached : AiTurnStatus::Failed, $exception->errorCode);
        } catch (ConnectorLinkRejectedException|AssistantSessionInvalidException|McpLinkRejectedException|FirstAccessPendingException) {
            $this->close($run, AiTurnStatus::Cancelled, AiTurnErrorCode::LinkLost);
        } catch (ConnectorUnavailableException|AssistantClientNotConfiguredException) {
            $this->close($run, AiTurnStatus::Failed, AiTurnErrorCode::ConnectorUnavailable);
        } catch (Throwable $exception) {
            Log::error('AI agent turn crashed', ['exception' => $exception::class, 'turn_id' => $turnId]);

            $this->close($run, AiTurnStatus::Failed, AiTurnErrorCode::Internal);
        }
    }

    /**
     * Closes a turn the queue gave up on (job crash, timeout): same close as a failure of the loop.
     */
    public function fail(int $turnId, AiTurnErrorCode $code): void
    {
        AiTurn::query()->whereKey($turnId)->whereIn('status', AiTurnStatus::activeValues())->update([
            'status' => AiTurnStatus::Failed,
            'error_code' => $code,
            'finished_at' => now(),
        ]);
    }

    private function execute(AgentRun $run): void
    {
        // Step 2: model, routing and instructions in force; a switched-off assistant makes no call
        $settings = $this->settings->effective();

        if (! $settings->enabled) {
            throw new AgentTurnLimitException(AiTurnErrorCode::SwitchedOff);
        }

        // Step 3: the credential of the person's link, opened from the session the message came from
        $session = $run->turn->personal_access_token_id === null
            ? null
            : PersonalAccessToken::query()->find($run->turn->personal_access_token_id);
        $credential = $session === null
            ? throw new AssistantSessionInvalidException
            : $this->links->credentialFor($run->turn->conversation->user, $session);

        // Step 4: the tools and the first messages
        $tools = $this->connector->tools($credential);
        $messages = $this->memory->messages($run, $settings, $tools);

        // Step 5: model rounds until a text answer
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

            foreach ($result->toolCalls as $call) {
                $this->assertCanCallTool($run);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'content' => $this->runTool($run, $credential, $call),
                ];
            }

            if ($run->aborted) {
                return;
            }
        }
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
        $run->toolCalls++;

        // Closed list: anything else never reaches the connector and leaves no step.
        if (! AssistantTools::isAllowed($call['name'])) {
            Log::warning('AI agent discarded a tool call that is not allowed');

            return ConnectorClient::REJECTED_RESULT;
        }

        // Decoded as objects so an empty `{}` of the model stays an object when sent on.
        $arguments = $call['arguments'] === '' ? new stdClass : json_decode($call['arguments']);

        if (is_array($arguments) && $arguments === []) {
            $arguments = new stdClass;
        }

        if (! $arguments instanceof stdClass) {
            return self::INVALID_ARGUMENTS_RESULT;
        }

        $index = $this->openStep($run, $call['name'], AssistantTools::operation($arguments));

        try {
            $result = $this->connector->call($credential, $call['name'], $arguments);
        } catch (ConnectorUnavailableException|ConnectorLinkRejectedException $exception) {
            $this->closeStep($run, $index, 'failed');

            throw $exception;
        }

        $this->closeStep($run, $index, $result['is_error'] ? 'failed' : 'done');

        return $result['text'];
    }

    private function complete(AgentRun $run, AiEffectiveSettings $settings, AgentModelResult $result): void
    {
        $text = trim($result->text);

        if ($text === '') {
            throw new AgentModelException(AiTurnErrorCode::StreamError);
        }

        $turn = $run->turn;

        // One transaction: the turn leaves `running` only together with its stored answer.
        DB::transaction(function () use ($run, $settings, $turn, $text): void {
            $closed = AiTurn::query()->whereKey($turn->id)->where('status', AiTurnStatus::Running)->update([
                'status' => AiTurnStatus::Completed,
                'assistant_text' => $text,
                'usage' => json_encode($run->usageForStorage()),
                'finished_at' => now(),
            ]);

            // Cancelled while the model was answering: nothing to keep.
            if ($closed === 0) {
                return;
            }

            $message = $turn->conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $text,
                'model' => $run->model ?? $settings->modelId,
                'input_tokens' => $run->inputTokens,
                'output_tokens' => $run->outputTokens,
            ]);

            AiTurn::query()->whereKey($turn->id)->update(['assistant_message_id' => $message->id]);
            $turn->conversation->update(['last_message_at' => now()]);
        });
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
            'state' => 'running',
            'operation' => $operation,
        ];
        $this->save($run, ['steps' => $run->steps]);

        return $index;
    }

    private function closeStep(AgentRun $run, int $index, string $state): void
    {
        $run->steps[$index]['state'] = $state;
        $this->save($run, ['steps' => $run->steps]);
    }

    private function close(AgentRun $run, AiTurnStatus $status, AiTurnErrorCode $code): void
    {
        foreach ($run->steps as $index => $step) {
            if ($step['state'] === 'running') {
                $run->steps[$index]['state'] = 'failed';
            }
        }

        AiTurn::query()->whereKey($run->turn->id)->whereIn('status', AiTurnStatus::activeValues())->update([
            'status' => $status,
            'error_code' => $code,
            'assistant_text' => $run->text === '' ? null : $run->text,
            'steps' => json_encode($run->steps),
            'usage' => json_encode($run->usageForStorage()),
            'finished_at' => now(),
        ]);
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
