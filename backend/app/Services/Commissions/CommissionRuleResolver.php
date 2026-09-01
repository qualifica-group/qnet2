<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\CommissionRecipient;
use App\DataObjects\Commissions\CommissionResolutionContext;
use App\DataObjects\Commissions\ResolvedCommissionRule;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use Illuminate\Database\Eloquent\Builder;

final class CommissionRuleResolver
{
    /** Gradini 1-3 (spec 0089 D-4): a rule scoped to the role's own recipient. */
    private const array PERSONAL_SCOPES = [
        CommissionApplicationScope::Product,
        CommissionApplicationScope::ProductCategory,
        CommissionApplicationScope::Recipient,
    ];

    /** Gradini 4-5: the pre-0089 role-wide rule, unchanged (AC-019). */
    private const array ROLE_SCOPES = [
        CommissionApplicationScope::Product,
        CommissionApplicationScope::ProductCategory,
    ];

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
                origin: $this->originOf($configuration),
            );
        }

        return $matches;
    }

    /**
     * The 5-step chain of spec 0089 D-4: the destination's own recipient
     * rules (gradini 1-3, product then category then the recipient-wide
     * RECIPIENT scope) win outright over the role-wide rules (gradini 4-5) —
     * they are never even queried once a personal rule matches. The
     * tie-break (priority desc, valid_from desc, id desc) applies only
     * within a single gradino, never across them.
     */
    private function resolveRole(
        CommissionResolutionContext $context,
        CommissionRecipientRole $role,
    ): ?CommissionConfiguration {
        $recipient = $context->recipients[$role->value] ?? null;

        if ($recipient !== null) {
            foreach (self::PERSONAL_SCOPES as $scope) {
                $configuration = $this->firstMatch($context, $role, $scope, $recipient);

                if ($configuration !== null) {
                    return $configuration;
                }
            }
        }

        foreach (self::ROLE_SCOPES as $scope) {
            $configuration = $this->firstMatch($context, $role, $scope, null);

            if ($configuration !== null) {
                return $configuration;
            }
        }

        return null;
    }

    /**
     * A single gradino's query. $recipient !== null targets gradini 1-3
     * (recipient_type/recipient_id pinned to it); $recipient === null targets
     * gradini 4-5 and MUST filter `whereNull('recipient_type')` — without it
     * a personal rule belonging to a DIFFERENT recipient would win as if it
     * were role-wide (risk R-2, covered by AC-005).
     */
    private function firstMatch(
        CommissionResolutionContext $context,
        CommissionRecipientRole $role,
        CommissionApplicationScope $scope,
        ?CommissionRecipient $recipient,
    ): ?CommissionConfiguration {
        if ($scope === CommissionApplicationScope::ProductCategory && $context->productCategoryId === null) {
            return null;
        }

        $query = CommissionConfiguration::query()
            ->where('recipient_role', $role)
            ->where('application_scope', $scope)
            ->where('status', CommissionConfigurationStatus::Active)
            ->whereDate('valid_from', '<=', $context->referenceDate->format('Y-m-d'))
            ->where(fn (Builder $query) => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $context->referenceDate->format('Y-m-d')))
            ->orderByDesc('priority')
            ->orderByDesc('valid_from')
            ->orderByDesc('id');

        $recipient === null
            ? $query->whereNull('recipient_type')
            : $query->where('recipient_type', $recipient->type)->where('recipient_id', $recipient->id);

        match ($scope) {
            CommissionApplicationScope::Product => $query->where('product_id', $context->productId),
            CommissionApplicationScope::ProductCategory => $query->where('product_category_id', $context->productCategoryId),
            CommissionApplicationScope::Recipient => null,
        };

        return $query->first();
    }

    /**
     * RECIPIENT origin (spec 0089 D-6) whenever the winning rule carries a
     * recipient, whatever its scope — the operator-facing distinction is
     * "personal rule" vs "role rule on product/category", the fine
     * traceability already lives on `commission_configuration_id`.
     */
    private function originOf(CommissionConfiguration $configuration): CommissionOrigin
    {
        if ($configuration->recipient_type !== null) {
            return CommissionOrigin::Recipient;
        }

        return $configuration->application_scope === CommissionApplicationScope::Product
            ? CommissionOrigin::Product
            : CommissionOrigin::ProductCategory;
    }
}
