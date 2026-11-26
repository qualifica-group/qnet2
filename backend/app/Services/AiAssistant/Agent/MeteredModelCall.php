<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiTurnErrorCode;
use App\Services\AiAssistant\Costs\AiCallUsage;
use App\Services\AiAssistant\Costs\AiReservation;
use App\Services\AiAssistant\Costs\AiSpendLedger;
use App\Services\AiAssistant\Costs\AiSpendLimitReached;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use App\Services\AiAssistant\Settings\OpenRouterCatalog;
use App\Services\AiAssistant\Settings\OpenRouterCatalogUnavailableException;
use Throwable;

/**
 * One model call inside the cost ledger (spec 0433, D-7): the price ceiling of the allowed
 * endpoints prices the reservation and goes to OpenRouter as `provider.max_price`, the call
 * runs, then the real cost replaces the reservation. A failure before any of the response arrived
 * frees it; one after part of it arrived, or an unknown one, is settled (declared cost, else the reservation).
 */
final class MeteredModelCall
{
    public function __construct(
        private readonly OpenRouterAgentClient $model,
        private readonly OpenRouterCatalog $catalog,
        private readonly AiSpendLedger $ledger,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  callable(string): void  $onText
     *
     * @throws AgentModelException the model could not be reached or failed
     * @throws AgentTurnLimitException a monthly cap stops the turn, before the call or after its cost
     */
    public function call(AgentRun $run, AiEffectiveSettings $settings, array $messages, array $tools, callable $onText): AgentModelResult
    {
        // Step 1: the ceiling price of the endpoints the routing allows
        try {
            $prices = $this->catalog->priceCeiling($settings->modelId, (bool) $settings->routing['zdr']);
        } catch (OpenRouterCatalogUnavailableException) {
            throw new AgentModelException(AiTurnErrorCode::ModelUnavailable);
        }

        // Evaluated before the reservation: an error here is not a call that may have been billed
        $limitReasoning = $this->catalog->supportsReasoning($settings->modelId);

        // Step 2: reserve the highest cost this call can have; over a cap, no call is made
        $bytes = strlen((string) json_encode([$messages, $tools], JSON_UNESCAPED_UNICODE));

        try {
            $reservation = $this->ledger->reserve($run->turn->userMessage, $bytes, OpenRouterAgentClient::maxTokens(), $prices);
        } catch (AiSpendLimitReached $limit) {
            throw new AgentTurnLimitException($this->codeFor($limit->scope));
        }

        // Step 3: the call; a failure before any byte of the response charges nothing, one after it
        // may have been charged: it is settled with the declared cost, or the reservation, and flagged
        try {
            $result = $this->model->complete($messages, $tools, $run->turn->ai_conversation_id, $settings, $prices, $onText, $limitReasoning);
        } catch (AgentModelException $exception) {
            $this->settleFailure($run, $settings, $reservation, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            // Unknown failure: the provider may already have billed, so the reservation (the upper bound) is charged
            $this->settleFailure($run, $settings, $reservation, new AgentModelException(AiTurnErrorCode::ModelUnavailable, true));

            throw $exception;
        }

        // Step 4: the real cost replaces the reservation
        $usage = AiCallUsage::fromOpenRouter($result->model ?? $settings->modelId, $result->provider, $result->rawUsage);
        $settlement = $this->ledger->settle($reservation, $usage);
        $run->addUsage($usage);

        if ($settlement->limitReached !== null) {
            throw new AgentTurnLimitException($this->codeFor($settlement->limitReached));
        }

        return $result;
    }

    private function settleFailure(AgentRun $run, AiEffectiveSettings $settings, AiReservation $reservation, AgentModelException $failure): void
    {
        if (! $failure->responseStarted) {
            $this->ledger->release($reservation);

            return;
        }

        $usage = AiCallUsage::fromOpenRouter($settings->modelId, null, $failure->rawUsage);
        $this->ledger->settle($reservation, $usage);
        $run->addUsage($usage);
    }

    private function codeFor(string $scope): AiTurnErrorCode
    {
        return $scope === AiSpendLimitReached::SCOPE_PERSON ? AiTurnErrorCode::LimitPerson : AiTurnErrorCode::LimitTotal;
    }
}
