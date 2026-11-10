<?php

declare(strict_types=1);

namespace App\Tables\Contracts\Concerns;

use App\Services\Contracts\ContractVisibilityScope;
use App\Tables\CategoryTabs\RevenueCategoryQuoteIds;
use App\Tables\Contracts\ContractAdvancedFilterCatalog;
use App\Tables\Shared\CommercialListFilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The advanced filters of the `contracts` domain, out of
 * ContractsTableDefinition (file-size limit, engineering.md §6). Roles and
 * categories live on the contract's offer.
 */
trait HasContractListFilters
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return [...app(CommercialListFilterApplier::class)->presetFilters('contracts', Auth::user(), ContractVisibilityScope::seesBeyondOwnRecords(Auth::user())), ...ContractAdvancedFilterCatalog::advancedFilters()];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        $applyCategories = static function (Builder $contracts, array $categoryIds): void {
            $contracts->whereIn('contracts.quote_id', RevenueCategoryQuoteIds::subquery($categoryIds));
        };

        return app(CommercialListFilterApplier::class)->apply($query, $name, $value, Auth::user(), $applyCategories, 'quote')
            || parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }
}
