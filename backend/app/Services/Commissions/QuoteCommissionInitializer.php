<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\AppliedCommissionDraft;
use App\DataObjects\Commissions\CommissionCalculationInput;
use App\DataObjects\Commissions\CommissionResolutionContext;
use App\DataObjects\Commissions\QuoteCommissionDefaultsData;
use App\Enums\CommissionRecipientRole;
use App\Models\Product;
use App\Models\Quote;

final class QuoteCommissionInitializer
{
    public function __construct(
        private readonly CommissionRuleResolver $resolver,
        private readonly CommissionCalculator $calculator,
        private readonly CommissionRecipientResolver $recipients,
    ) {}

    /** @return array<int, AppliedCommissionDraft> */
    public function initialize(QuoteCommissionDefaultsData $data): array
    {
        $quote = $data->quoteId === null ? null : Quote::findOrFail($data->quoteId);
        $product = Product::query()->with('category')->findOrFail($data->productId);
        // Submitted role ids win over the quote's persisted ones: the caller is
        // an open form whose roles may already have been changed but not saved.
        $recipients = $this->recipients->resolve(
            $product,
            $data->commercialId ?? $quote?->commercial_id,
            $data->reporterId ?? $quote?->reporter_id,
            $data->supervisorId ?? $quote?->supervisor_id,
        );

        $rules = $this->resolver->resolve(new CommissionResolutionContext(
            productId: $product->id,
            productCategoryId: $product->category_id,
            roles: CommissionRecipientRole::cases(),
            referenceDate: $data->referenceDate,
            // Spec 0089 D-5: already resolved above, zero extra queries.
            recipients: $recipients,
        ));

        $drafts = [];

        foreach ($rules as $rule) {
            $recipient = $recipients[$rule->role->value];

            if ($recipient === null) {
                continue;
            }

            $drafts[] = new AppliedCommissionDraft(
                role: $rule->role,
                recipient: $recipient,
                type: $rule->type,
                value: $rule->value,
                calculatedAmount: $this->calculator->calculate(new CommissionCalculationInput(
                    type: $rule->type,
                    value: $rule->value,
                    lineNetAmount: $data->lineNetAmount,
                )),
                internalNote: $rule->internalNote,
                origin: $rule->origin,
                configurationId: $rule->configurationId,
            );
        }

        return $drafts;
    }
}
