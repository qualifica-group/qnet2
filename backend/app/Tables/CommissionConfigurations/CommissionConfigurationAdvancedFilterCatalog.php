<?php

namespace App\Tables\CommissionConfigurations;

use App\Enums\AdvancedFilterType;

final class CommissionConfigurationAdvancedFilterCatalog
{
    public static function advancedFilters(): array
    {
        return [
            ['name' => 'name', 'label' => 'commissionConfigurations.advancedFilters.name', 'type' => AdvancedFilterType::Text, 'order' => 1, 'required' => false, 'visible' => true, 'width' => 'md', 'multiple' => false, 'target' => 'name'],
            ['name' => 'valid_from_range', 'label' => 'commissionConfigurations.advancedFilters.validFrom', 'type' => AdvancedFilterType::DateRange, 'order' => 2, 'required' => false, 'visible' => true, 'width' => 'md', 'multiple' => false, 'target' => 'valid_from'],
            ['name' => 'valid_until_range', 'label' => 'commissionConfigurations.advancedFilters.validUntil', 'type' => AdvancedFilterType::DateRange, 'order' => 3, 'required' => false, 'visible' => true, 'width' => 'md', 'multiple' => false, 'target' => 'valid_until'],
        ];
    }
}
