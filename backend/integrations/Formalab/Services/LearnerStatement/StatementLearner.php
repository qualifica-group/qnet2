<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

/**
 * One learner of the prospetto: the Formalab payload item, its computed
 * hours and amounts (LearnerFigures) and its partner data (CommissionBook).
 */
final readonly class StatementLearner
{
    /**
     * @param  array<string, mixed>  $payload  the raw `classe.discenti` item
     */
    public function __construct(
        public array $payload,
        public string $taxCode,
        public ?bool $passed,
        public int|float $costHours,
        public float $cost,
        public int|float $revenueHours,
        public float $revenue,
        public float $margin,
        public ?string $partner,
        public float $percentage,
        public float $amount,
    ) {}

    public function firstName(): string
    {
        return (string) ($this->payload['name'] ?? '');
    }

    public function lastName(): string
    {
        return (string) ($this->payload['last_name'] ?? '');
    }

    /** "Nome Cognome", as the legacy partner detail rows print it. */
    public function fullName(): string
    {
        return $this->firstName().' '.$this->lastName();
    }

    /** An integer field of the payload (hours/minutes of attendance or absence). */
    public function int(string $key): int
    {
        return (int) ($this->payload[$key] ?? 0);
    }
}
