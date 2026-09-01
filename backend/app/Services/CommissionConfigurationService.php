<?php

namespace App\Services;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\DataObjects\CommissionConfigurations\UpdateCommissionConfigurationData;
use App\Models\CommissionConfiguration;

class CommissionConfigurationService
{
    public function loadDetail(CommissionConfiguration $configuration): CommissionConfiguration
    {
        return $configuration->load(['productCategory', 'product', 'recipient']);
    }

    public function create(CreateCommissionConfigurationData $data): CommissionConfiguration
    {
        return $this->loadDetail(CommissionConfiguration::create($data->attributes()));
    }

    public function update(
        CommissionConfiguration $configuration,
        UpdateCommissionConfigurationData $data,
    ): CommissionConfiguration {
        $configuration->fill($data->attributes)->save();

        return $this->loadDetail($configuration->fresh());
    }

    public function delete(CommissionConfiguration $configuration): void
    {
        abort_if(
            $configuration->appliedCommissions()->exists(),
            409,
            __('commission_configurations.referenced_delete'),
        );

        $configuration->delete();
    }
}
