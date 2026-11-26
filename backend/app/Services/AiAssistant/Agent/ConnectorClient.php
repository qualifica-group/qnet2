<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * MCP client of the assistant entry of the connector (spec 0433, internal_contract): JSON-RPC
 * over HTTP with the Laravel client, no dependency. The connector answers every call as
 * `text/event-stream` with a single `data:` event and keeps no session.
 *
 * Tool results go back to the model as the connector wrote them: nothing is cut or rewritten.
 */
final class ConnectorClient
{
    private const string MCP_PATH = '/assistant/mcp';

    private const string TOOLS_CACHE_KEY = 'ai-assistant.connector-tools';

    private const int TOOLS_CACHE_SECONDS = 300;

    public const string REJECTED_RESULT = '{"connettore":{"esito":"fallita","codice":"richiesta_non_valida"},"dati":null}';

    public static function isConfigured(): bool
    {
        return filled(config('ai-assistant.connector.secret')) && filled(config('ai-assistant.connector.url'));
    }

    /**
     * Allowed tools as OpenAI function definitions, in the fixed order of the allowed list.
     * The list is the same for everybody, so it is cached: each `tools/list` leaves an audit row.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ConnectorUnavailableException
     * @throws ConnectorLinkRejectedException
     */
    public function tools(string $credential): array
    {
        $cached = Cache::get(self::TOOLS_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $published = [];
        foreach ($this->rpc($credential, 'tools/list', [])['tools'] ?? [] as $tool) {
            if (is_array($tool) && isset($tool['name']) && AssistantTools::isAllowed((string) $tool['name'])) {
                $published[(string) $tool['name']] = $tool;
            }
        }

        $definitions = [];
        foreach (AssistantTools::ALLOWED as $name) {
            if (isset($published[$name])) {
                $definitions[] = ['type' => 'function', 'function' => [
                    'name' => $name,
                    'description' => (string) ($published[$name]['description'] ?? ''),
                    'parameters' => $published[$name]['inputSchema'] ?? ['type' => 'object'],
                ]];
            }
        }

        if ($definitions === []) {
            $this->unavailable('empty_tool_list');
        }

        Cache::put(self::TOOLS_CACHE_KEY, $definitions, self::TOOLS_CACHE_SECONDS);

        return $definitions;
    }

    /**
     * Runs one tool and returns the text of the result for the model and whether the connector marked
     * it as an error. A refusal or an error of the tool is a result like any other (the model decides);
     * only transport problems throw.
     *
     * @param  object  $arguments  the arguments object as the model wrote it
     * @return array{text: string, is_error: bool}
     *
     * @throws ConnectorUnavailableException
     * @throws ConnectorLinkRejectedException
     */
    public function call(string $credential, string $name, object $arguments): array
    {
        $result = $this->rpc($credential, 'tools/call', ['name' => $name, 'arguments' => $arguments]);
        $text = $result['content'][0]['text'] ?? null;

        return is_string($text)
            ? ['text' => $text, 'is_error' => (bool) ($result['isError'] ?? false)]
            : ['text' => self::REJECTED_RESULT, 'is_error' => true];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed> the JSON-RPC `result`; a JSON-RPC `error` of tools/call becomes a fixed rejected result
     */
    private function rpc(string $credential, string $method, array $params): array
    {
        $body = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], JSON_THROW_ON_ERROR);

        try {
            $response = $this->request($credential)->withBody($body, 'application/json')->post(self::url());
        } catch (ConnectionException) {
            $this->unavailable('connection');
        }

        if ($response->status() === 401) {
            throw new ConnectorLinkRejectedException;
        }

        if (! $response->successful()) {
            $this->unavailable('http_status', ['status' => $response->status()]);
        }

        $message = $this->message($response);

        if (isset($message['error'])) {
            // Unknown tool or malformed request: the model gets a fixed text, never the raw error.
            return $method === 'tools/call' ? ['content' => [['text' => self::REJECTED_RESULT]], 'isError' => true] : $this->unavailable('rpc_error');
        }

        return is_array($message['result'] ?? null) ? $message['result'] : $this->unavailable('no_result');
    }

    /**
     * The last `data:` line of the event stream (a single event in practice), or a plain JSON body.
     *
     * @return array<string, mixed>
     */
    private function message(Response $response): array
    {
        $payload = trim($response->body());
        $lines = preg_grep('/^data:/', explode("\n", str_replace("\r\n", "\n", $payload))) ?: [];

        if ($lines !== []) {
            $payload = trim(substr((string) end($lines), strlen('data:')));
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->unavailable('malformed_body');
        }

        return is_array($decoded) ? $decoded : $this->unavailable('malformed_body');
    }

    private function request(string $credential): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => (string) config('ai-assistant.connector.protocol_version'),
            'x-assistant-secret' => (string) config('ai-assistant.connector.secret'),
            'x-assistant-link-credential' => $credential,
        ];

        // The connector accepts only its public host in the Host header, while QNET2 reaches it on 127.0.0.1.
        if (filled(config('ai-assistant.connector.host'))) {
            $headers['Host'] = (string) config('ai-assistant.connector.host');
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('ai-assistant.connector.timeout_seconds'))
            ->connectTimeout((int) config('ai-assistant.connector.connect_timeout_seconds'));
    }

    private static function url(): string
    {
        return rtrim((string) config('ai-assistant.connector.url'), '/').self::MCP_PATH;
    }

    /**
     * Logs a sanitized reason (never the secret, the credential, the URL or a body) and throws.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws ConnectorUnavailableException
     */
    private function unavailable(string $reason, array $context = []): never
    {
        Log::warning('AI agent connector call failed', ['reason' => $reason] + $context);

        throw new ConnectorUnavailableException;
    }
}
