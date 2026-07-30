<?php

namespace App\Tables\PaymentMethods;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `payment-methods` domain (spec 0068): a
 * small lookup table — every entry here is a direct-column filter, handled
 * entirely by the generic default (no domain override needed). `is_active`
 * (Switch) and `payment_days` (NumberRange) are the two entries the spec
 * freezes; `name` is also exposed, mirroring RewardStatusAdvancedFilterCatalog.
 */
final class PaymentMethodAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'paymentMethods.advancedFilters.name',
                'type' => AdvancedFilterType::Text,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'name',
            ],
            [
                'name' => 'is_active',
                'label' => 'paymentMethods.advancedFilters.isActive',
                'type' => AdvancedFilterType::Switch,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_active',
            ],
            [
                'name' => 'payment_days_range',
                'label' => 'paymentMethods.advancedFilters.paymentDaysRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'payment_days',
            ],
        ];
    }
}
