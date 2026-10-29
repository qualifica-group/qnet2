<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\DataObjects\Commissions\CommissionCalculationInput;
use App\DataObjects\Commissions\CommissionRecipient;
use App\DataObjects\Commissions\CommissionResolutionContext;
use App\DataObjects\Invoices\SupplierCommission;
use App\Enums\CommissionRecipientRole;
use App\Models\QuoteLine;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\CommissionRuleResolver;
use DateTimeImmutable;

/**
 * The supplier's commission on a sold line, billed by an institution proforma
 * (spec 0288, D-4). Unlike the offer commissions (spec 0145, margin base) the
 * base is the line's sale net amount, and the rule is resolved afresh: the
 * typology switch and the stored quote_line_commissions are not read.
 */
final class SupplierCommissionCalculator
{
    private const string ZERO = '0.00';

    public function __construct(
        private readonly CommissionRuleResolver $resolver,
        private readonly CommissionCalculator $calculator,
    ) {}

    /** Expects `product` loaded on the line. */
    public function forLine(QuoteLine $line, DateTimeImmutable $referenceDate): SupplierCommission
    {
        // Step 1: the rule of the line's supplier (spec 0320, D-8) on its product (product, then category chain, then role-wide)
        $role = CommissionRecipientRole::Supplier;
        $supplierId = $line->supplier_id;
        $rule = $this->resolver->resolve(new CommissionResolutionContext(
            productId: $line->product->id,
            productCategoryId: $line->product->category_id,
            roles: [$role],
            referenceDate: $referenceDate,
            recipients: [$role->value => $supplierId === null ? null : new CommissionRecipient($role->recipientType(), $supplierId)],
        ))[0] ?? null;

        $base = (string) $line->net_amount;

        // Step 2: no rule bills zero, so the missing rule stays visible
        if ($rule === null) {
            return new SupplierCommission(null, null, $base, self::ZERO);
        }

        // Step 3: percentage of the sale net amount, or the fixed amount per line
        $amount = $this->calculator->calculate(new CommissionCalculationInput($rule->type, $rule->value, $base));

        return new SupplierCommission($rule->type, $rule->value, $base, $amount);
    }
}
