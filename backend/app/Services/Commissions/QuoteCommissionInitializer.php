<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\AppliedCommissionDraft;
use App\DataObjects\Commissions\CommissionCalculationInput;
use App\DataObjects\Commissions\CommissionRecipient;
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
    ) {}

    /** @return array<int, AppliedCommissionDraft> */
    public function initialize(QuoteCommissionDefaultsData $data): array
    {
        $quote = $data->quoteId === null ? null : Quote::findOrFail($data->quoteId);
        $product = Product::query()->with('category')->findOrFail($data->productId);
        $recipients = [
            CommissionRecipientRole::Commercial->value => $this->recipient('referent', $quote?->commercial_id ?? $data->commercialId),
            CommissionRecipientRole::Reporter->value => $this->recipient('referent', $quote?->reporter_id ?? $data->reporterId),
            CommissionRecipientRole::Supervisor->value => $this->recipient('user', $quote?->supervisor_id ?? $data->supervisorId),
            CommissionRecipientRole::Supplier->value => $this->recipient('registry', $product->supplier_id),
        ];

        $rules = $this->resolver->resolve(new CommissionResolutionContext(
            productId: $product->id,
            productCategoryId: $product->category_id,
            roles: CommissionRecipientRole::cases(),
            referenceDate: $data->referenceDate,
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

    private function recipient(string $type, ?int $id): ?CommissionRecipient
    {
        return $id === null ? null : new CommissionRecipient($type, $id);
    }
}
