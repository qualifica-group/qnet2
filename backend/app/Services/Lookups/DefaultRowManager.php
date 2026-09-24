<?php

declare(strict_types=1);

namespace App\Services\Lookups;

use Illuminate\Database\Eloquent\Model;

/**
 * `is_default` single-row-per-table enforcement for the PURE lookup
 * configurators that carry it (spec 0154, D-8): task_types, task_priorities,
 * task_importances. Generic on the model via a class-string (mirrors
 * LookupOrderManager) — task_categories has no `is_default` column at all
 * and never reaches this class.
 */
final class DefaultRowManager
{
    /**
     * A default row must stay selectable: refuses `is_default = true`
     * paired with `is_active = false` (spec 0154, D-8 — "a default must be
     * selectable"). Called with the row's EFFECTIVE values (submitted, else
     * the persisted one on a partial update), before the write.
     */
    public function assertSelectable(bool $isDefault, bool $isActive): void
    {
        if ($isDefault && ! $isActive) {
            abort(422, 'An inactive row cannot be the default: activate it first.');
        }
    }

    /**
     * Clears `is_default` on every OTHER row of $modelClass so at most one
     * stays true. Called from inside the caller's own transaction, right
     * after the row carrying `is_default = true` is written — the caller
     * owns the transaction boundary, this only owns the "at most one" rule.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function clearOthers(string $modelClass, int $exceptId): void
    {
        $modelClass::query()->where('id', '!=', $exceptId)->update(['is_default' => false]);
    }
}
