<?php

namespace App\Services\ApiDocs;

/**
 * Converts the API OpenAPI document into a Postman Collection v2.1:
 * one folder per tag, one request per operation, bearer auth through the
 * `{{api_key}}` variable. The client-login request stores the returned token in
 * `{{user_token}}`. Pure transformation, no dependency.
 */
class PostmanCollectionBuilder
{
    public const string COLLECTION_NAME = 'QNet API';

    private const string SCHEMA_URL = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

    private const string CLIENT_LOGIN_PATH = '/auth/client-login';

    private const string DEFAULT_FOLDER = 'Other';

    private const array HTTP_METHODS = ['get', 'put', 'post', 'delete', 'patch', 'head', 'options'];

    private const array BODY_METHODS = ['post', 'put', 'patch'];

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function build(array $document): array
    {
        $examples = new OpenApiExampleGenerator($document);

        // Step 1: group the operations into one folder per (first) tag
        $folders = [];

        foreach ($document['paths'] ?? [] as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (! in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }

                $folder = (string) ($operation['tags'][0] ?? self::DEFAULT_FOLDER);
                $folders[$folder][] = $this->request((string) $path, $method, $operation, $examples);
            }
        }

        // Step 2: assemble the collection
        return [
            'info' => [
                'name' => self::COLLECTION_NAME,
                'description' => (string) ($document['info']['description'] ?? ''),
                'schema' => self::SCHEMA_URL,
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [['key' => 'token', 'value' => '{{api_key}}', 'type' => 'string']],
            ],
            'variable' => [
                ['key' => 'base_url', 'value' => (string) ($document['servers'][0]['url'] ?? '')],
                ['key' => 'api_key', 'value' => ''],
                ['key' => 'user_token', 'value' => ''],
            ],
            'item' => array_map(
                static fn (string $name, array $items): array => ['name' => $name, 'item' => $items],
                array_keys($folders),
                array_values($folders),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function request(string $path, string $method, array $operation, OpenApiExampleGenerator $examples): array
    {
        $parameters = array_map($examples->resolve(...), $operation['parameters'] ?? []);

        $headers = [['key' => 'Accept', 'value' => 'application/json']];
        $request = [
            'method' => strtoupper($method),
            'description' => trim(($operation['summary'] ?? '')."\n\n".($operation['description'] ?? '')),
            'url' => $this->url($path, $parameters, $examples),
        ];

        if (in_array($method, self::BODY_METHODS, true) && isset($operation['requestBody'])) {
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
            $request['body'] = $this->body($operation['requestBody'], $examples);
        }

        $request['header'] = $headers;

        $item = ['name' => $operation['summary'] ?? $operation['operationId'] ?? $method.' '.$path, 'request' => $request];

        if ($method === 'post' && $path === self::CLIENT_LOGIN_PATH) {
            $item['event'] = [$this->storeUserTokenEvent()];
        }

        return $item;
    }

    /**
     * Test script that saves the user token returned by client-login, so the
     * integrator can switch the collection auth to `{{user_token}}`.
     *
     * @return array<string, mixed>
     */
    private function storeUserTokenEvent(): array
    {
        return [
            'listen' => 'test',
            'script' => [
                'type' => 'text/javascript',
                'exec' => [
                    'const body = pm.response.json();',
                    "if (body.data && body.data.token) { pm.collectionVariables.set('user_token', body.data.token); }",
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @return array<string, mixed>
     */
    private function url(string $path, array $parameters, OpenApiExampleGenerator $examples): array
    {
        $segments = array_values(array_filter(
            array_map(
                static fn (string $segment): string => preg_replace('/^\{(.+)\}$/', ':$1', $segment),
                explode('/', $path),
            ),
            static fn (string $segment): bool => $segment !== '',
        ));

        $variables = [];
        $query = [];

        foreach ($parameters as $parameter) {
            $example = $examples->example($parameter['schema'] ?? []);
            $value = is_scalar($example) ? (string) $example : '';
            $description = (string) ($parameter['description'] ?? '');

            if (($parameter['in'] ?? null) === 'path') {
                $variables[] = ['key' => $parameter['name'], 'value' => $value, 'description' => $description];
            } elseif (($parameter['in'] ?? null) === 'query') {
                $query[] = [
                    'key' => $parameter['name'],
                    'value' => $value,
                    'description' => $description,
                    'disabled' => ! ($parameter['required'] ?? false),
                ];
            }
        }

        $enabledQuery = array_filter($query, static fn (array $item): bool => ! $item['disabled']);

        return [
            'raw' => '{{base_url}}/'.implode('/', $segments).$this->queryString($enabledQuery),
            'host' => ['{{base_url}}'],
            'path' => $segments,
            'variable' => $variables,
            'query' => $query,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $query
     */
    private function queryString(array $query): string
    {
        if ($query === []) {
            return '';
        }

        return '?'.implode('&', array_map(static fn (array $item): string => $item['key'].'='.$item['value'], $query));
    }

    /**
     * @param  array<string, mixed>  $requestBody
     * @return array<string, mixed>
     */
    private function body(array $requestBody, OpenApiExampleGenerator $examples): array
    {
        $schema = $examples->resolve($requestBody)['content']['application/json']['schema'] ?? [];

        return [
            'mode' => 'raw',
            'raw' => json_encode($examples->example($schema), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'options' => ['raw' => ['language' => 'json']],
        ];
    }
}
