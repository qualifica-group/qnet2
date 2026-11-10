<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\ProductCategory;

/**
 * A group is a current root category plus the legacy roots that point at it
 * (`current_category_id`, spec 0374 D-9, D-12). A link counts only while both
 * ends are still roots and the target is not legacy.
 */
final class LinkedCategoryGroups
{
    /**
     * Replaces every linked legacy root with its current root, without duplicates.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public function currentRootIds(array $ids): array
    {
        $links = ProductCategory::query()
            ->whereIn('id', $ids)
            ->whereNull('parent_id')
            ->where('is_legacy', true)
            ->whereNotNull('current_category_id')
            ->pluck('current_category_id', 'id');

        if ($links->isEmpty()) {
            return array_values(array_unique($ids));
        }

        $validTargets = ProductCategory::query()
            ->whereIn('id', $links->values())
            ->whereNull('parent_id')
            ->where('is_legacy', false)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_map(
            static fn (int $id): int => in_array($links[$id] ?? null, $validTargets, true) ? $links[$id] : $id,
            $ids,
        )));
    }

    /**
     * Each given root followed by the legacy roots linked to it (by id), without duplicates.
     *
     * @param  array<int, int>  $rootIds
     * @return array<int, int>
     */
    public function withLinkedLegacy(array $rootIds): array
    {
        $current = ProductCategory::query()
            ->whereIn('id', $rootIds)
            ->whereNull('parent_id')
            ->where('is_legacy', false)
            ->pluck('id')
            ->all();
        $legacy = $current === [] ? collect() : ProductCategory::query()
            ->whereIn('current_category_id', $current)
            ->whereNull('parent_id')
            ->where('is_legacy', true)
            ->orderBy('id')
            ->get(['id', 'current_category_id'])
            ->groupBy('current_category_id');

        $group = [];

        foreach ($rootIds as $rootId) {
            $group[] = $rootId;
            array_push($group, ...($legacy[$rootId] ?? collect())->pluck('id')->all());
        }

        return array_values(array_unique($group));
    }
}
