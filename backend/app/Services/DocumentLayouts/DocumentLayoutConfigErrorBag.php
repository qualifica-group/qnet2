<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

/**
 * A minimal path => message collector shared across
 * DocumentLayoutConfigValidator and its split per-block validators (spec
 * 0069): each violation is recorded against its dotted path
 * (`config.body.blocks.3.runs.0.size`, per the data_contract's `validation`
 * section), one message per path — the first violation recorded for a path
 * wins, later ones for the SAME path are ignored (a path is normally only
 * ever validated once, so this only matters for defensive callers).
 */
final class DocumentLayoutConfigErrorBag
{
    /** @var array<string, string> */
    private array $errors = [];

    public function add(string $path, string $message): void
    {
        $this->errors[$path] ??= $message;
    }

    public function has(string $path): bool
    {
        return array_key_exists($path, $this->errors);
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->errors;
    }
}
