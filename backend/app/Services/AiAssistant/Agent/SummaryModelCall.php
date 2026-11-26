<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiTurnErrorCode;
use App\Services\AiAssistant\Memory\AiMemoryEntry;
use App\Services\AiAssistant\Memory\AiSummaryExecutor;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;

/**
 * The summary request of the memory run as one more model call of the turn (spec 0433, D-5, D-7):
 * the same model, through the cost ledger, so it is reserved, priced and written like any other call
 * and a monthly cap reached here closes the turn the same way.
 */
final class SummaryModelCall implements AiSummaryExecutor
{
    public function __construct(
        private readonly MeteredModelCall $model,
        private readonly AgentRun $run,
        private readonly AiEffectiveSettings $settings,
    ) {}

    /**
     * @throws AgentModelException
     * @throws AgentTurnLimitException
     */
    public function complete(array $request): string
    {
        $this->run->modelCalls++;

        $messages = array_map(fn (AiMemoryEntry $entry): array => ['role' => $entry->role, 'content' => $entry->text], $request);
        $result = $this->model->call($this->run, $this->settings, $messages, [], static fn (string $fragment) => null);

        // No text is the same failure as a stream that ends without an answer.
        if (trim($result->text) === '') {
            throw new AgentModelException(AiTurnErrorCode::StreamError);
        }

        return $result->text;
    }
}
