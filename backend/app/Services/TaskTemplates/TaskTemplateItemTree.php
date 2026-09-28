<?php

declare(strict_types=1);

namespace App\Services\TaskTemplates;

use App\Models\TaskTemplateItem;
use Illuminate\Support\Collection;

/**
 * Pure helper over a self-referencing `parent_id` tree of `TaskTemplateItem`
 * rows (spec 0172, D-1/D-6). The only operation it offers is the safe
 * deletion order: TaskTemplateItemWriter::sync() (dropped rows) and
 * TaskTemplateService::delete() (a whole template) both need every row
 * deleted through Eloquent's own `::delete()`, deepest descendant first, so
 * `HasAttachments`' `deleting` hook fires at every level instead of letting a
 * shallower delete's DB-level `cascadeOnDelete` silently sweep away
 * still-undeleted descendants (which never fires that hook).
 */
final class TaskTemplateItemTree
{
    /**
     * Orders $items so every row appears AFTER all of its descendants that
     * are ALSO present in $items — a parent whose child is not in this same
     * collection (kept elsewhere, already re-parented) is treated as a leaf
     * here, which is exactly the "safe to delete now" order.
     *
     * @param  Collection<int, TaskTemplateItem>  $items
     * @return Collection<int, TaskTemplateItem>
     */
    public static function deepestFirst(Collection $items): Collection
    {
        $byId = $items->keyBy('id');

        return $items
            ->sortByDesc(static fn (TaskTemplateItem $item): int => self::depth($item, $byId))
            ->values();
    }

    /**
     * @param  Collection<int, TaskTemplateItem>  $byId
     */
    private static function depth(TaskTemplateItem $item, Collection $byId): int
    {
        $depth = 0;
        $current = $item;

        while ($current->parent_id !== null && $byId->has($current->parent_id)) {
            /** @var TaskTemplateItem $current */
            $current = $byId->get($current->parent_id);
            $depth++;
        }

        return $depth;
    }
}
