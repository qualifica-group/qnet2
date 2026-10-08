<?php

namespace App\Tables\WorkOrderPaymentStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `work-order-payment-statuses` domain (spec
 * 0201): direct-column filters handled by the generic default.
 */
final class WorkOrderPaymentStatusAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'workOrderPaymentStatuses.advancedFilters.name',
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
                'label' => 'workOrderPaymentStatuses.advancedFilters.isActive',
                'type' => AdvancedFilterType::Switch,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_active',
            ],
            [
                'name' => 'allows_delivery',
                'label' => 'workOrderPaymentStatuses.advancedFilters.allowsDelivery',
                'type' => AdvancedFilterType::Switch,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'allows_delivery',
            ],
        ];
    }
}
