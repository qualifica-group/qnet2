<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Models\CommissionSettlement;

/**
 * The "document" block of the settlement detail (spec 0267, D-3): what the
 * agent declared and the signed difference with the settlement. Each amount
 * follows the permission field of the settlement amount it mirrors, so a role
 * that cannot see the settlement amount cannot read it back through the document.
 */
final class SettlementDocumentView
{
    /** Document amount => settlement fields that must all be visible to show it. */
    private const array GATING_FIELDS = [
        'taxable_amount' => ['taxable_amount'],
        'vat_amount' => ['vat_amount'],
        'withholding_amount' => ['withholding_amount'],
        'enasarco_amount' => ['enasarco_agent_amount'],
        'total_amount' => ['taxable_amount', 'vat_amount'],
    ];

    public function __construct(private readonly DocumentToleranceChecker $checker) {}

    /**
     * @param  array<int, string>  $hiddenFields  settlement fields the actor cannot see
     * @return array<string, mixed>|null
     */
    public function present(CommissionSettlement $settlement, array $hiddenFields): ?array
    {
        if ($settlement->document_number === null) {
            return null;
        }

        $declared = $this->checker->declaredOf($settlement);
        $differences = $this->checker->differences($declared, $this->checker->referenceOf($settlement));
        $visible = array_filter(self::GATING_FIELDS, fn (array $fields): bool => array_intersect($fields, $hiddenFields) === []);

        return [
            'number' => $settlement->document_number,
            'date' => $settlement->document_date?->format('Y-m-d'),
            ...array_intersect_key($declared, $visible),
            'received_at' => $settlement->document_received_at,
            'received_by' => $settlement->documentReceiver === null ? null : ['id' => $settlement->documentReceiver->id, 'name' => $settlement->documentReceiver->name],
            'differences' => array_intersect_key($differences, $visible),
        ];
    }
}
