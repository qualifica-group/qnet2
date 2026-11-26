<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

/**
 * The highest price, field by field, among the endpoints the routing allows (spec 0433, D-7).
 * The same ceiling prices the reservation and goes to OpenRouter as `provider.max_price`, so a
 * dearer endpoint is never used and the reservation bounds the real cost.
 */
final readonly class AiPriceCeiling
{
    private const int TOKENS_PER_MILLION = 1_000_000;

    /**
     * @param  string  $promptUsdPerToken  input, cache discount ignored
     * @param  string  $completionUsdPerToken  output (reasoning included)
     * @param  string  $requestUsd  flat price per request
     */
    public function __construct(
        public string $promptUsdPerToken,
        public string $completionUsdPerToken,
        public string $requestUsd = '0',
    ) {}

    /**
     * Guaranteed upper bound of one call: a token is never shorter than one byte, so the UTF-8
     * bytes of the request plus a fixed margin bound the input tokens; the output is capped by
     * max_tokens, with reasoning limited under it.
     */
    public function upperBoundUsd(int $requestBytes, int $maxTokens, int $marginTokens): string
    {
        $input = AiMoney::mul(AiMoney::from($this->promptUsdPerToken), $requestBytes + $marginTokens);
        $output = AiMoney::mul(AiMoney::from($this->completionUsdPerToken), $maxTokens);

        return AiMoney::add(AiMoney::add($input, $output), AiMoney::from($this->requestUsd));
    }

    /**
     * The `provider.max_price` block, in dollars per million tokens (request: dollars per request).
     *
     * @return array{prompt: string, completion: string, request: string}
     */
    public function maxPrice(): array
    {
        $perMillion = fn (string $perToken): string => rtrim(rtrim(AiMoney::mul(AiMoney::from($perToken), self::TOKENS_PER_MILLION), '0'), '.') ?: '0';

        return [
            'prompt' => $perMillion($this->promptUsdPerToken),
            'completion' => $perMillion($this->completionUsdPerToken),
            'request' => rtrim(rtrim(AiMoney::from($this->requestUsd), '0'), '.') ?: '0',
        ];
    }
}
