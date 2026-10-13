<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

/**
 * Every amount of one settlement (spec 0213, D-2), all bcmath strings with 2 decimals.
 */
final readonly class SettlementAmounts
{
    public function __construct(
        public string $accruals,
        public string $adjustments,
        public string $taxable,
        public string $vat,
        public string $withholding,
        public string $enasarcoBase,
        public string $enasarcoAgent,
        public string $enasarcoPrincipal,
        public string $netPayable,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toAttributes(): array
    {
        return [
            'accruals_amount' => $this->accruals,
            'adjustments_amount' => $this->adjustments,
            'taxable_amount' => $this->taxable,
            'vat_amount' => $this->vat,
            'withholding_amount' => $this->withholding,
            'enasarco_base_amount' => $this->enasarcoBase,
            'enasarco_agent_amount' => $this->enasarcoAgent,
            'enasarco_principal_amount' => $this->enasarcoPrincipal,
            'net_payable' => $this->netPayable,
        ];
    }
}
