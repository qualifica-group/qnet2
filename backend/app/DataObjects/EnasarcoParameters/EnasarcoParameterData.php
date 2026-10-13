<?php

namespace App\DataObjects\EnasarcoParameters;

/**
 * Validated payload for creating or updating the ENASARCO parameters of a
 * year (spec 0213, D-3). Decimals stay strings: they are bcmath operands.
 */
final readonly class EnasarcoParameterData
{
    public function __construct(
        public int $year,
        public string $totalRate,
        public string $agentSharePercent,
        public string $singleFirmCeiling,
        public string $multiFirmCeiling,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $notes = $data['notes'] ?? null;

        return new self(
            year: (int) $data['year'],
            totalRate: (string) $data['total_rate'],
            agentSharePercent: (string) $data['agent_share_percent'],
            singleFirmCeiling: (string) $data['single_firm_ceiling'],
            multiFirmCeiling: (string) $data['multi_firm_ceiling'],
            notes: $notes === null || $notes === '' ? null : (string) $notes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'year' => $this->year,
            'total_rate' => $this->totalRate,
            'agent_share_percent' => $this->agentSharePercent,
            'single_firm_ceiling' => $this->singleFirmCeiling,
            'multi_firm_ceiling' => $this->multiFirmCeiling,
            'notes' => $this->notes,
        ];
    }
}
