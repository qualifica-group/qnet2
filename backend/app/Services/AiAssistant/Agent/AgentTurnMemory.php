<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Services\AiAssistant\Memory\AiConversationMemory;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use App\Services\AiAssistant\Settings\OpenRouterCatalog;
use App\Services\AiAssistant\Settings\OpenRouterCatalogUnavailableException;

/**
 * The messages a turn starts from (spec 0433, D-5): the memory plans them inside the context
 * window of the chosen model and, when the old part no longer fits, the turn summarises it
 * first with a metered call of the same model. Whatever the turn exchanges with the tools
 * afterwards is appended by the loop and is never summarised inside the turn.
 */
final class AgentTurnMemory
{
    public function __construct(
        private readonly AiConversationMemory $memory,
        private readonly AgentMessageBuilder $messages,
        private readonly MeteredModelCall $model,
        private readonly OpenRouterCatalog $catalog,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<array<string, mixed>>
     *
     * @throws AgentModelException
     * @throws AgentTurnLimitException
     */
    public function messages(AgentRun $run, AiEffectiveSettings $settings, array $tools): array
    {
        $turn = $run->turn;
        $window = $this->contextWindow($settings->modelId);

        // Step 1: plan inside the window
        $plan = $this->memory->plan($this->messages->input($turn, $settings->instructions, $tools, $window));

        // Step 2: too long: fold the old part into the summary, then plan again from the cut
        if ($plan->needsSummary) {
            $this->memory->summarize($turn->conversation, $plan, new SummaryModelCall($this->model, $run, $settings));
            $plan = $this->memory->plan($this->messages->input($turn, $settings->instructions, $tools, $window));
        }

        return $this->messages->request($plan);
    }

    /** The window OpenRouter lists for the model, or the prudent configured one when it does not say. */
    private function contextWindow(string $modelId): int
    {
        try {
            $window = $this->catalog->contextWindow($modelId);
        } catch (OpenRouterCatalogUnavailableException) {
            $window = null;
        }

        return $window ?? (int) config('ai-assistant.memory.fallback_context_tokens');
    }
}
