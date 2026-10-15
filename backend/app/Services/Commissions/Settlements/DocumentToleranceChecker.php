<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Models\CommissionSettlement;

/**
 * Pure comparison of the amounts declared on the agent document with the ones
 * of the settlement (spec 0267, D-3). Both sides are keyed by the short amount
 * names: taxable_amount, vat_amount, withholding_amount, enasarco_amount,
 * total_amount (taxable + VAT). bcmath only; the tolerance is an argument, read
 * from config by the caller.
 */
final class DocumentToleranceChecker
{
    /** Short amount names compared, in the order the document lists them. */
    public const array AMOUNTS = ['taxable_amount', 'vat_amount', 'withholding_amount', 'enasarco_amount', 'total_amount'];

    /** Request/column prefix of the document amounts. */
    public const string FIELD_PREFIX = 'document_';

    /**
     * The amounts over the tolerance, keyed by document field (`document_vat_amount`).
     *
     * @param  array<string, string>  $declared
     * @param  array<string, string>  $reference
     * @return array<string, array{difference: string, tolerance: string}>
     */
    public function violations(array $declared, array $reference, string $tolerance): array
    {
        $violations = [];

        foreach ($this->differences($declared, $reference) as $amount => $difference) {
            $violation = $this->violation($difference, $tolerance);

            if ($violation !== null) {
                $violations[self::FIELD_PREFIX.$amount] = $violation;
            }
        }

        return $violations;
    }

    /**
     * The absolute difference and the tolerance when the signed difference is over it.
     *
     * @return array{difference: string, tolerance: string}|null
     */
    public function violation(string $signedDifference, string $tolerance): ?array
    {
        $absolute = ltrim($signedDifference, '-');

        return bccomp($absolute, $tolerance, 2) > 0 ? ['difference' => $absolute, 'tolerance' => bcadd($tolerance, '0', 2)] : null;
    }

    /**
     * Signed difference (document minus settlement) per amount.
     *
     * @param  array<string, string>  $declared
     * @param  array<string, string>  $reference
     * @return array<string, string>
     */
    public function differences(array $declared, array $reference): array
    {
        $differences = [];

        foreach (self::AMOUNTS as $amount) {
            $differences[$amount] = bcsub($declared[$amount], $reference[$amount], 2);
        }

        return $differences;
    }

    /**
     * The settlement amounts a document is compared with.
     *
     * @return array<string, string>
     */
    public function referenceOf(CommissionSettlement $settlement): array
    {
        return [
            'taxable_amount' => (string) $settlement->taxable_amount,
            'vat_amount' => (string) $settlement->vat_amount,
            'withholding_amount' => (string) $settlement->withholding_amount,
            'enasarco_amount' => (string) $settlement->enasarco_agent_amount,
            'total_amount' => bcadd((string) $settlement->taxable_amount, (string) $settlement->vat_amount, 2),
        ];
    }

    /**
     * The amounts stored on the settlement document.
     *
     * @return array<string, string>
     */
    public function declaredOf(CommissionSettlement $settlement): array
    {
        return array_combine(
            self::AMOUNTS,
            array_map(fn (string $amount): string => (string) $settlement->getAttribute(self::FIELD_PREFIX.$amount), self::AMOUNTS),
        );
    }
}
