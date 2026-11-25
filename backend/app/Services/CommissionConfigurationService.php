<?php

namespace App\Services;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\DataObjects\CommissionConfigurations\UpdateCommissionConfigurationData;
use App\Models\CommissionConfiguration;
use Illuminate\Support\Facades\DB;

class CommissionConfigurationService
{
    private const string SCOPE_LISTS_LOG_DESCRIPTION = 'Commission scope lists change';

    public function loadDetail(CommissionConfiguration $configuration): CommissionConfiguration
    {
        return $configuration->load(['productCategories', 'products', 'recipient']);
    }

    public function create(CreateCommissionConfigurationData $data): CommissionConfiguration
    {
        return DB::transaction(function () use ($data): CommissionConfiguration {
            $configuration = CommissionConfiguration::create($data->attributes());
            $configuration->productCategories()->sync($data->productCategoryIds);
            $configuration->products()->sync($data->productIds);

            return $this->loadDetail($configuration);
        });
    }

    public function update(
        CommissionConfiguration $configuration,
        UpdateCommissionConfigurationData $data,
    ): CommissionConfiguration {
        return DB::transaction(function () use ($configuration, $data): CommissionConfiguration {
            // Step 1: snapshot the lists, then save the rule itself
            $before = $this->scopeLists($configuration);
            $configuration->fill($data->attributes)->save();

            // Step 2: replace the lists that were submitted (or emptied by a scope change)
            if ($data->productCategoryIds !== null) {
                $configuration->productCategories()->sync($data->productCategoryIds);
            }
            if ($data->productIds !== null) {
                $configuration->products()->sync($data->productIds);
            }

            // Step 3: history entry for the pivots, which the model log cannot see
            $this->logScopeListsChange($configuration, $before);

            return $this->loadDetail($configuration->fresh());
        });
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

    /** @return array{product_category_ids: list<int>, product_ids: list<int>} sorted ids */
    private function scopeLists(CommissionConfiguration $configuration): array
    {
        $lists = [
            'product_category_ids' => $configuration->productCategories()->pluck('product_categories.id'),
            'product_ids' => $configuration->products()->pluck('products.id'),
        ];

        return array_map(static fn ($ids): array => $ids->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all(), $lists);
    }

    /**
     * Spec 0430 D-8: one `activity_log` entry carrying only the lists that really
     * differ, in the `attributes`/`old` shape of the model log.
     *
     * @param  array{product_category_ids: list<int>, product_ids: list<int>}  $before
     */
    private function logScopeListsChange(CommissionConfiguration $configuration, array $before): void
    {
        $after = $this->scopeLists($configuration);
        $changed = array_filter($after, static fn (array $ids, string $key): bool => $ids !== $before[$key], ARRAY_FILTER_USE_BOTH);

        if ($changed === []) {
            return;
        }

        activity($configuration->getTable())
            ->performedOn($configuration)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => array_intersect_key($before, $changed)])
            ->log(self::SCOPE_LISTS_LOG_DESCRIPTION);
    }
}
