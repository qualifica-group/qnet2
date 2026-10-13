<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

/**
 * The fiscal terms a settlement is computed with (spec 0213, D-2/D-3): the
 * snapshot of the agent profile and of the ENASARCO year. A null group means
 * that component is off (no VAT, no withholding, no ENASARCO).
 */
final readonly class SettlementTerms
{
    public function __construct(
        public ?string $vatRate = null,
        public ?string $withholdingRate = null,
        public ?string $withholdingBasePercent = null,
        public ?string $enasarcoTotalRate = null,
        public ?string $enasarcoAgentSharePercent = null,
        public ?string $enasarcoCeiling = null,
    ) {}

    public function hasWithholding(): bool
    {
        return $this->withholdingRate !== null && $this->withholdingBasePercent !== null;
    }

    public function hasEnasarco(): bool
    {
        return $this->enasarcoTotalRate !== null
            && $this->enasarcoAgentSharePercent !== null
            && $this->enasarcoCeiling !== null;
    }
}
