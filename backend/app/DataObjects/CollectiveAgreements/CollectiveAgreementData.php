<?php

namespace App\DataObjects\CollectiveAgreements;

/**
 * Validated payload for creating or partially updating a collective
 * agreement (spec 0272 D-3). `levels` absent means "unchanged" (null),
 * present means the ordered list REPLACES the saved levels.
 */
final readonly class CollectiveAgreementData
{
    /**
     * @param  array<string, mixed>  $submitted  validated name/code keys
     * @param  array<int, array{id: int|null, code: string, description: string|null}>|null  $levels
     */
    private function __construct(private array $submitted, private ?array $levels) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $levels = null;

        if (array_key_exists('levels', $data)) {
            $levels = array_values(array_map(
                static fn (array $level): array => [
                    'id' => isset($level['id']) ? (int) $level['id'] : null,
                    'code' => (string) $level['code'],
                    'description' => $level['description'] ?? null,
                ],
                (array) ($data['levels'] ?? []),
            ));
        }

        return new self(array_intersect_key($data, array_flip(['name', 'code'])), $levels);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->submitted;
    }

    /**
     * @return array<int, array{id: int|null, code: string, description: string|null}>|null
     */
    public function levels(): ?array
    {
        return $this->levels;
    }
}
