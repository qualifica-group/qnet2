<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Settings;

use App\Services\AiAssistant\Costs\AiMoney;
use App\Services\AiAssistant\Costs\AiPriceCeiling;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Read-only view of OpenRouter for the settings page (spec 0433): the models the
 * assistant may use (tools, text in and text out) and, for one model, the
 * providers that keep no data. The browser never talks to OpenRouter; answers
 * are cached for a few minutes. Prices are exposed in dollars per million tokens.
 */
class OpenRouterCatalog
{
    private const int TOKENS_PER_MILLION = 1_000_000;

    private const string TOOLS_PARAMETER = 'tools';

    /**
     * Models with tool calling and text-only input and output, sorted by name.
     *
     * @return list<array{id: string, name: string, context_length: int, pricing: array{prompt: string|null, completion: string|null, input_cache_read: string|null, request: string|null}}>
     */
    public function models(): array
    {
        return $this->cached('models', function (): array {
            $body = $this->get('/models', ['supported_parameters' => self::TOOLS_PARAMETER]);

            $models = [];
            foreach ($body['data'] ?? [] as $model) {
                if (! is_array($model) || ! $this->isTextOnlyWithTools($model) || ! is_string($model['id'] ?? null)) {
                    continue;
                }
                $models[] = [
                    'id' => $model['id'],
                    'name' => (string) ($model['name'] ?? $model['id']),
                    'context_length' => (int) ($model['context_length'] ?? 0),
                    'pricing' => $this->pricing($model['pricing'] ?? []),
                ];
            }
            usort($models, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            return $models;
        });
    }

    public function has(string $modelId): bool
    {
        return in_array($modelId, array_column($this->models(), 'id'), true);
    }

    /**
     * Providers of one model that support tools and keep no data (zero retention).
     * Speed and latency are null when OpenRouter does not publish them.
     *
     * @return list<array{name: string, tag: string, zdr: bool, uptime_pct: float|null, throughput_tps: float|null, latency_ms: float|null, pricing: array{prompt: string|null, completion: string|null, input_cache_read: string|null}}>
     */
    public function providers(string $modelId): array
    {
        return $this->cached('providers:'.$modelId, function () use ($modelId): array {
            $zdrTags = $this->zdrTags($modelId);
            $body = $this->get('/models/'.$modelId.'/endpoints');

            $providers = [];
            foreach ($body['data']['endpoints'] ?? [] as $endpoint) {
                $tag = is_array($endpoint) ? ($endpoint['tag'] ?? null) : null;
                if (! is_string($tag) || ! in_array($tag, $zdrTags, true)
                    || ! in_array(self::TOOLS_PARAMETER, $endpoint['supported_parameters'] ?? [], true)) {
                    continue;
                }
                $providers[] = [
                    'name' => (string) ($endpoint['provider_name'] ?? $tag),
                    'tag' => $tag,
                    'zdr' => true,
                    'uptime_pct' => $this->number($endpoint['uptime_last_30m'] ?? null),
                    'throughput_tps' => $this->median($endpoint['throughput_last_30m'] ?? null),
                    'latency_ms' => $this->median($endpoint['latency_last_30m'] ?? null),
                    'pricing' => array_diff_key($this->pricing($endpoint['pricing'] ?? []), ['request' => 1]),
                ];
            }

            return $providers;
        });
    }

    /**
     * The dearest price, field by field, among the endpoints of a model that support tools (and,
     * with $zdrOnly, retain no prompt): the ceiling the cost reservation and `provider.max_price`
     * use (spec 0433, D-7). Dollars per token and per request, not per million.
     *
     * @throws OpenRouterCatalogUnavailableException when OpenRouter lists no usable endpoint
     */
    public function priceCeiling(string $modelId, bool $zdrOnly): AiPriceCeiling
    {
        $prices = $this->cached('ceiling:'.$modelId.':'.(int) $zdrOnly, function () use ($modelId, $zdrOnly): array {
            $zdrTags = $zdrOnly ? $this->zdrTags($modelId) : null;
            $ceiling = ['prompt' => null, 'completion' => null, 'request' => '0'];

            foreach ($this->get('/models/'.$modelId.'/endpoints')['data']['endpoints'] ?? [] as $endpoint) {
                $pricing = is_array($endpoint) ? ($endpoint['pricing'] ?? null) : null;
                // A negative price ("-1" on routers with a variable price) cannot bound a cost: the endpoint is left out.
                if (! is_array($pricing) || AiMoney::fromNonNegative($pricing['prompt'] ?? null) === null || AiMoney::fromNonNegative($pricing['completion'] ?? null) === null
                    || ! in_array(self::TOOLS_PARAMETER, $endpoint['supported_parameters'] ?? [], true)
                    || ($zdrTags !== null && ! in_array($endpoint['tag'] ?? null, $zdrTags, true))) {
                    continue;
                }
                foreach (['prompt', 'completion', 'request'] as $field) {
                    $price = AiMoney::from($pricing[$field] ?? 0);
                    $ceiling[$field] = $ceiling[$field] === null ? $price : AiMoney::max($ceiling[$field], $price);
                }
            }

            if ($ceiling['prompt'] === null) {
                throw new OpenRouterCatalogUnavailableException;
            }

            return $ceiling;
        });

        return new AiPriceCeiling($prices['prompt'], $prices['completion'], $prices['request']);
    }

    /**
     * Tags of the endpoints of a model that retain no prompt (Safety Retention excluded).
     *
     * @return list<string>
     */
    private function zdrTags(string $modelId): array
    {
        $body = $this->get('/endpoints/zdr');

        $tags = [];
        foreach ($body['data'] ?? [] as $endpoint) {
            if (is_array($endpoint) && ($endpoint['model_id'] ?? null) === $modelId
                && ($endpoint['safety_retention'] ?? false) === false && is_string($endpoint['tag'] ?? null)) {
                $tags[] = $endpoint['tag'];
            }
        }

        return $tags;
    }

    /**
     * @param  array<string, mixed>  $model
     */
    private function isTextOnlyWithTools(array $model): bool
    {
        return in_array(self::TOOLS_PARAMETER, $model['supported_parameters'] ?? [], true)
            && ($model['architecture']['input_modalities'] ?? null) === ['text']
            && ($model['architecture']['output_modalities'] ?? null) === ['text'];
    }

    /**
     * @param  array<string, mixed>  $pricing  dollars per token, as strings
     * @return array{prompt: string|null, completion: string|null, input_cache_read: string|null, request: string|null}
     */
    private function pricing(mixed $pricing): array
    {
        $pricing = is_array($pricing) ? $pricing : [];

        return [
            'prompt' => $this->perMillion($pricing['prompt'] ?? null),
            'completion' => $this->perMillion($pricing['completion'] ?? null),
            'input_cache_read' => $this->perMillion($pricing['input_cache_read'] ?? null),
            // Dollars per request, not per token: passed through unchanged.
            'request' => isset($pricing['request']) && (float) $pricing['request'] > 0 ? (string) $pricing['request'] : null,
        ];
    }

    private function perMillion(mixed $perToken): ?string
    {
        if (! is_numeric($perToken)) {
            return null;
        }

        $value = rtrim(rtrim(sprintf('%.6F', (float) $perToken * self::TOKENS_PER_MILLION), '0'), '.');

        return $value === '' ? '0' : $value;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /** OpenRouter gives percentiles ({p50, ...}) only to callers with a key; a plain number is accepted too. */
    private function median(mixed $value): ?float
    {
        return $this->number(is_array($value) ? ($value['p50'] ?? null) : $value);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->client()->get(config('ai-assistant.openrouter.base_url').$path, $query);
        } catch (ConnectionException) {
            throw new OpenRouterCatalogUnavailableException;
        }

        $body = $response->successful() ? $response->json() : null;

        if (! is_array($body)) {
            throw new OpenRouterCatalogUnavailableException;
        }

        return $body;
    }

    private function client(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout((int) config('ai-assistant.settings.catalog_timeout_seconds'))
            ->withoutRedirecting();
        $key = config('ai-assistant.openrouter.api_key');

        // The key only unlocks speed and latency; the lists are public.
        return filled($key) ? $request->withToken($key) : $request;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    private function cached(string $key, callable $resolver): mixed
    {
        return Cache::remember(
            'ai-assistant:catalog:'.$key,
            (int) config('ai-assistant.settings.models_cache_seconds'),
            $resolver,
        );
    }
}
