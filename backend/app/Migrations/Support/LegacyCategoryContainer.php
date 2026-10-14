<?php

namespace App\Migrations\Support;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * The single root every legacy product category hangs under (user directive
 * 2026-10-14): the "[OLD] ..." taxonomy and the "[OLD] Costi" branch are
 * history only, so they are kept out of the qnet catalogue's own roots
 * instead of sitting beside them or under "Consulenza".
 *
 * The container is created on first need, NOT active and NOT selectable, like
 * the history it holds. It carries no business function, so every legacy root
 * keeps its own without breaking the one-per-chain rule (spec 0023).
 */
final class LegacyCategoryContainer
{
    public const string NAME = 'Old category';

    public function id(): int
    {
        return ProductCategory::firstOrCreate(
            ['name' => self::NAME, 'parent_id' => null],
            ['is_active' => false, 'is_selectable' => false],
        )->id;
    }

    /**
     * Moves every legacy node that does not hang under another legacy node
     * into the container: a legacy root, one created detached, one an earlier
     * seed nested under "Consulenza", and the "[OLD] Costi" root. Re-running
     * moves nothing a second time.
     *
     * A direct `parent_id` write, as the legacy nesting always was: the moved
     * nodes keep the values they were imported with. Per-model, so the
     * activity log records each move.
     */
    public function gather(): int
    {
        $tops = $this->legacyTops();

        if ($tops->isEmpty()) {
            return 0;
        }

        $containerId = $this->id();

        $tops->each(fn (ProductCategory $category) => $category->update(['parent_id' => $containerId]));

        return $tops->count();
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function legacyTops(): Collection
    {
        $qnetIds = ProductCategory::query()
            ->whereNull('old_id')
            ->where('name', '!=', self::NAME)
            ->select('id');

        return ProductCategory::query()
            ->where(fn ($query) => $query
                ->whereNotNull('old_id')
                ->where(fn ($parent) => $parent->whereNull('parent_id')->orWhereIn('parent_id', $qnetIds)))
            ->orWhere(fn ($query) => $query->whereNull('parent_id')->where('name', CostProductCatalogue::ROOT))
            ->get();
    }
}
