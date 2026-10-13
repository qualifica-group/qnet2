<?php

namespace App\DataObjects\AgentFiscalProfiles;

/**
 * Validated payload for creating or updating an agent fiscal profile (spec
 * 0212). The rate/base and the mandate type are normalized to null when the
 * withholding / ENASARCO switch is off, so a stale value never persists.
 */
final readonly class AgentFiscalProfileData
{
    public function __construct(
        public int $referentId,
        public bool $withholdingEnabled,
        public ?string $withholdingRate,
        public ?string $withholdingBasePercent,
        public bool $enasarcoEnabled,
        public ?string $enasarcoMandateType,
        public string $validFrom,
        public ?string $validUntil,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $withholding = (bool) $data['withholding_enabled'];
        $enasarco = (bool) $data['enasarco_enabled'];

        return new self(
            referentId: (int) $data['referent_id'],
            withholdingEnabled: $withholding,
            withholdingRate: $withholding ? self::nullableString($data['withholding_rate'] ?? null) : null,
            withholdingBasePercent: $withholding ? self::nullableString($data['withholding_base_percent'] ?? null) : null,
            enasarcoEnabled: $enasarco,
            enasarcoMandateType: $enasarco ? self::nullableString($data['enasarco_mandate_type'] ?? null) : null,
            validFrom: (string) $data['valid_from'],
            validUntil: self::nullableString($data['valid_until'] ?? null),
            notes: self::nullableString($data['notes'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'referent_id' => $this->referentId,
            'withholding_enabled' => $this->withholdingEnabled,
            'withholding_rate' => $this->withholdingRate,
            'withholding_base_percent' => $this->withholdingBasePercent,
            'enasarco_enabled' => $this->enasarcoEnabled,
            'enasarco_mandate_type' => $this->enasarcoMandateType,
            'valid_from' => $this->validFrom,
            'valid_until' => $this->validUntil,
            'notes' => $this->notes,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
