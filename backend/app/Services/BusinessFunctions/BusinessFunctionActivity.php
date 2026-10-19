<?php

namespace App\Services\BusinessFunctions;

use App\Models\BusinessFunction;

/**
 * The single authority on the EFFECTIVE activity of a business function
 * (spec 0280 D-1/D-5): a function is effectively inactive when its own
 * `is_active` is false OR any ancestor's is. The descendants' own flag is
 * never rewritten; the cascade is resolved here in batch (one query over
 * id/parent_id/is_active), never a query or a walk per row. Mirrors
 * SectorActivity.
 */
final class BusinessFunctionActivity
{
    /** @var array<int, array{parent_id: int|null, is_active: bool, name: string}>|null */
    private ?array $nodes = null;

    /** @var array<int, int>|null */
    private ?array $inactiveIds = null;

    /**
     * Ids of every effectively inactive function (own-inactive plus all their
     * descendants), memoized per instance.
     *
     * @return array<int, int>
     */
    public function inactiveIds(): array
    {
        return $this->inactiveIds ??= $this->resolveInactiveIds();
    }

    public function isActive(int $businessFunctionId): bool
    {
        return ! in_array($businessFunctionId, $this->inactiveIds(), true);
    }

    /**
     * The closest ancestor whose own flag is off, the reason a function is
     * inactive without being switched off itself (spec 0280 D-8).
     *
     * @return array{id: int, name: string}|null
     */
    public function inactiveAncestor(int $businessFunctionId): ?array
    {
        $nodes = $this->nodes();
        $seen = [$businessFunctionId => true];
        $currentId = $nodes[$businessFunctionId]['parent_id'] ?? null;

        // `$seen` guards a corrupted cycle from looping.
        while ($currentId !== null && ! isset($seen[$currentId]) && isset($nodes[$currentId])) {
            if (! $nodes[$currentId]['is_active']) {
                return ['id' => $currentId, 'name' => $nodes[$currentId]['name']];
            }

            $seen[$currentId] = true;
            $currentId = $nodes[$currentId]['parent_id'];
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function resolveInactiveIds(): array
    {
        $ownInactive = [];
        $childrenByParent = [];
        foreach ($this->nodes() as $id => $node) {
            if (! $node['is_active']) {
                $ownInactive[] = $id;
            }
            if ($node['parent_id'] !== null) {
                $childrenByParent[$node['parent_id']][] = $id;
            }
        }

        // Depth-first over the in-memory adjacency; `$seen` also guards a
        // corrupted cycle from looping.
        $seen = array_fill_keys($ownInactive, true);
        $stack = $ownInactive;
        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (! isset($seen[$childId])) {
                    $seen[$childId] = true;
                    $stack[] = $childId;
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * @return array<int, array{parent_id: int|null, is_active: bool, name: string}>
     */
    private function nodes(): array
    {
        return $this->nodes ??= BusinessFunction::query()
            ->get(['id', 'parent_id', 'is_active', 'name'])
            ->mapWithKeys(static fn (BusinessFunction $function): array => [$function->id => [
                'parent_id' => $function->parent_id,
                'is_active' => (bool) $function->is_active,
                'name' => (string) $function->name,
            ]])
            ->all();
    }
}
