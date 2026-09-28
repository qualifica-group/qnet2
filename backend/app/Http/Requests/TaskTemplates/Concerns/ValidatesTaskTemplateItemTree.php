<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskTemplates\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cross-field validation for the nested sub-item tree carried inside the flat
 * `items[]` array (spec 0172, D-1/D-2): `key`/`parent_key` are request-scoped
 * identifiers, never persisted — the same "client key resolved against the
 * same request" pattern as ValidatesTaskTemplateStages::
 * assertItemStageKeysResolve(), one level deeper (a row's parent is another
 * row of `items`, not a separate array).
 * App\Services\TaskTemplates\TaskTemplateItemWriter trusts every
 * `parent_key` it receives has already been resolved here.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesTaskTemplateItemTree
{
    private const int MAX_SUB_ITEM_DEPTH = 3;

    /**
     * Validates, over the submitted rows: `key` uniqueness among non-null
     * keys (data_contract), `parent_key` resolution to a PRECEDING row's
     * `key` (AC-004), the depth cap (AC-003), the "no stage on a sub-item"
     * rule (D-3, AC-005) and the "offset never exceeds the direct parent's"
     * rule (D-4, AC-006).
     */
    protected function assertItemTreeIsValid(Validator $validator): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $this->assertItemKeysAreDistinct($validator, $items);
        $this->assertItemParentageIsValid($validator, $items);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function assertItemKeysAreDistinct(Validator $validator, array $items): void
    {
        $seenKeys = [];

        foreach ($items as $index => $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;

            if ($key === null) {
                continue;
            }

            if (in_array($key, $seenKeys, true)) {
                $validator->errors()->add("items.{$index}.key", 'This key was already used by an earlier row.');

                continue;
            }

            $seenKeys[] = $key;
        }
    }

    /**
     * Walks the rows in submission order, resolving each `parent_key`
     * against the `key`s already seen (never a later row, D-2) and deriving
     * each row's depth from its resolved parent's — a structural guarantee
     * against cycles, since a row can only ever point backwards.
     *
     * @param  array<int, mixed>  $items
     */
    private function assertItemParentageIsValid(Validator $validator, array $items): void
    {
        /** @var array<string, int> $indexByKey */
        $indexByKey = [];
        /** @var array<int, int> $depthByIndex root = 0 */
        $depthByIndex = [];

        foreach ($items as $index => $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;
            $parentKey = is_array($row) ? ($row['parent_key'] ?? null) : null;

            if ($parentKey === null) {
                $depthByIndex[$index] = 0;
            } elseif (! array_key_exists($parentKey, $indexByKey)) {
                $validator->errors()->add("items.{$index}.parent_key", 'This parent_key does not match an earlier row.');
            } else {
                $parentIndex = $indexByKey[$parentKey];
                $depth = ($depthByIndex[$parentIndex] ?? 0) + 1;
                $depthByIndex[$index] = $depth;

                if ($depth > self::MAX_SUB_ITEM_DEPTH) {
                    $validator->errors()->add("items.{$index}.parent_key", 'Sub-items cannot be nested more than '.self::MAX_SUB_ITEM_DEPTH.' levels below a root row.');
                } else {
                    $this->assertSubItemHasNoStage($validator, $index, $row);
                    $this->assertSubItemOffsetWithinParent($validator, $index, $row, $items[$parentIndex]);
                }
            }

            if ($key !== null && ! array_key_exists($key, $indexByKey)) {
                $indexByKey[$key] = $index;
            }
        }
    }

    private function assertSubItemHasNoStage(Validator $validator, int|string $index, mixed $row): void
    {
        $stageKey = is_array($row) ? ($row['stage_key'] ?? null) : null;

        if ($stageKey !== null) {
            $validator->errors()->add("items.{$index}.stage_key", 'A sub-item cannot have its own stage.');
        }
    }

    private function assertSubItemOffsetWithinParent(Validator $validator, int|string $index, mixed $row, mixed $parentRow): void
    {
        $offset = $this->numericOffset($row);
        $parentOffset = $this->numericOffset($parentRow);

        if ($offset !== null && $parentOffset !== null && $offset > $parentOffset) {
            $validator->errors()->add("items.{$index}.due_offset_days", "A sub-item's offset cannot exceed its parent's.");
        }
    }

    private function numericOffset(mixed $row): ?int
    {
        $value = is_array($row) ? ($row['due_offset_days'] ?? null) : null;

        return is_numeric($value) ? (int) $value : null;
    }
}
