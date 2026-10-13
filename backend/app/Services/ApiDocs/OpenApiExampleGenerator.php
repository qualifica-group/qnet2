<?php

namespace App\Services\ApiDocs;

/**
 * Builds example values from OpenAPI schemas and resolves local `$ref`s, with a
 * guard against reference cycles. Used to give the Postman collection a body
 * the integrator can send as-is.
 */
class OpenApiExampleGenerator
{
    private const int MAX_DEPTH = 8;

    private const array FORMAT_EXAMPLES = [
        'date-time' => '2026-01-01T00:00:00+00:00',
        'date' => '2026-01-01',
        'email' => 'user@example.com',
        'uri' => 'https://example.com',
    ];

    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(private readonly array $document) {}

    /**
     * Follows `$ref` chains to the referenced node; an unresolvable or cyclic
     * reference yields an empty node.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function resolve(array $node): array
    {
        $seen = [];

        while (isset($node['$ref'])) {
            $ref = (string) $node['$ref'];

            if (isset($seen[$ref])) {
                return [];
            }

            $seen[$ref] = true;
            $node = $this->lookup($ref);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function example(array $schema, int $depth = 0, array $trail = []): mixed
    {
        $ref = $schema['$ref'] ?? null;

        if ($ref !== null) {
            if (in_array($ref, $trail, true) || $depth > self::MAX_DEPTH) {
                return null;
            }

            $trail[] = $ref;
        }

        $schema = $this->resolve($schema);

        return $this->exampleOfResolved($schema, $depth + 1, $trail);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $trail
     */
    private function exampleOfResolved(array $schema, int $depth, array $trail): mixed
    {
        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (isset($schema['enum'][0])) {
            return $schema['enum'][0];
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $combinator) {
            if (isset($schema[$combinator])) {
                return $this->exampleOfCombinator($combinator, $schema[$combinator], $depth, $trail);
            }
        }

        $type = $schema['type'] ?? null;
        $type = is_array($type) ? (array_values(array_diff($type, ['null']))[0] ?? 'null') : $type;

        return match ($type) {
            'object' => $this->exampleOfObject($schema, $depth, $trail),
            'array' => isset($schema['items']) ? [$this->example($schema['items'], $depth, $trail)] : [],
            'integer', 'number' => $schema['minimum'] ?? 1,
            'boolean' => true,
            'string' => self::FORMAT_EXAMPLES[$schema['format'] ?? ''] ?? 'string',
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @param  array<int, string>  $trail
     */
    private function exampleOfCombinator(string $combinator, array $members, int $depth, array $trail): mixed
    {
        if ($combinator === 'allOf') {
            $merged = [];

            foreach ($members as $member) {
                $example = $this->example($member, $depth, $trail);
                $merged = is_array($example) ? array_merge($merged, $example) : $merged;
            }

            return $merged;
        }

        foreach ($members as $member) {
            if (($member['type'] ?? null) !== 'null') {
                return $this->example($member, $depth, $trail);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $trail
     * @return array<string, mixed>|object
     */
    private function exampleOfObject(array $schema, int $depth, array $trail): array|object
    {
        $example = [];

        foreach ($schema['properties'] ?? [] as $name => $property) {
            $example[$name] = $this->example($property, $depth, $trail);
        }

        return $example === [] ? (object) [] : $example;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookup(string $ref): array
    {
        if (! str_starts_with($ref, '#/')) {
            return [];
        }

        $node = $this->document;

        foreach (explode('/', substr($ref, 2)) as $segment) {
            $node = $node[str_replace(['~1', '~0'], ['/', '~'], $segment)] ?? null;

            if (! is_array($node)) {
                return [];
            }
        }

        return $node;
    }
}
