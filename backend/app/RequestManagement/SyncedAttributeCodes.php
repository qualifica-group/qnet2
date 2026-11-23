<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The codes an offer and its work orders share one value of (spec 0425,
 * D-1/D-2): assigned in the Commessa of these categories with "Sincronizzato
 * con l'offerta" on, and in their Offerta too. A code in one context only is
 * never synced, whatever its flag says.
 */
final class SyncedAttributeCodes
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @return list<string>
     */
    public function forCategories(Collection $categories): array
    {
        // One cheap probe keeps every resolve of an installation without
        // synced fields at the cost it had before this feature.
        if ($categories->isEmpty() || ! $this->anyFlagged()) {
            return [];
        }

        $flagged = $this->codes($categories, AttributeContext::WorkOrder, onlySynced: true);

        if ($flagged === []) {
            return [];
        }

        return array_values(array_intersect($flagged, $this->codes($categories, AttributeContext::Quote, onlySynced: false)));
    }

    private function anyFlagged(): bool
    {
        return DB::table('attribute_category')
            ->where('context', AttributeContext::WorkOrder->value)
            ->where('syncs_with_quote', true)
            ->exists();
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @return list<string>
     */
    private function codes(Collection $categories, AttributeContext $context, bool $onlySynced): array
    {
        return $categories
            ->flatMap(fn (ProductCategory $category): Collection => $this->hierarchy->effectiveAttributes($category, $context))
            ->filter(static fn (array $row): bool => ! $onlySynced || $row['syncs_with_quote'])
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }
}
