<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\CommissionResolutionContext;
use App\DataObjects\Commissions\ResolvedCommissionRule;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;

final class CommissionRuleResolver
{
    /**
     * @return array<int, ResolvedCommissionRule>
     */
    public function resolve(CommissionResolutionContext $context): array
    {
        $matches = [];

        foreach ($context->roles as $role) {
            $configuration = $this->resolveRole($context, $role);

            if ($configuration === null) {
                continue;
            }

            $matches[] = new ResolvedCommissionRule(
                configurationId: $configuration->id,
                role: $role,
                type: $configuration->commission_type,
                value: $configuration->value,
                internalNote: $configuration->internal_note,
                origin: $configuration->application_scope === CommissionApplicationScope::Product
                    ? CommissionOrigin::Product
                    : CommissionOrigin::ProductCategory,
            );
        }

        return $matches;
    }

    private function resolveRole(
        CommissionResolutionContext $context,
        CommissionRecipientRole $role,
    ): ?CommissionConfiguration {
        foreach ([CommissionApplicationScope::Product, CommissionApplicationScope::ProductCategory] as $scope) {
            if ($scope === CommissionApplicationScope::ProductCategory && $context->productCategoryId === null) {
                continue;
            }

            $query = CommissionConfiguration::query()
                ->where('recipient_role', $role)
                ->where('application_scope', $scope)
                ->where('status', CommissionConfigurationStatus::Active)
                ->whereDate('valid_from', '<=', $context->referenceDate->format('Y-m-d'))
                ->where(fn ($query) => $query
                    ->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $context->referenceDate->format('Y-m-d')))
                ->orderByDesc('priority')
                ->orderByDesc('valid_from')
                ->orderByDesc('id');

            $scope === CommissionApplicationScope::Product
                ? $query->where('product_id', $context->productId)
                : $query->where('product_category_id', $context->productCategoryId);

            $configuration = $query->first();

            if ($configuration !== null) {
                return $configuration;
            }
        }

        return null;
    }
}
