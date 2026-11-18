<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;

/**
 * The open sub-tasks a parent's Completa closes with it (spec 0418 D-1): every
 * open child and, below each OPEN child only, its own open children. The
 * sub-tree of a child that is already closed is left alone, the same "direct
 * children" rule spec 0123 D-6 counts with. Unscoped on purpose: what may be
 * shown or closed by the actor is decided by the callers.
 */
final class TaskOpenSubtree
{
    /**
     * Pre-order (a node before its children, siblings by `subtask_position`/
     * `id`): the order of the "Ripartizione" rows and of the time slots.
     * Reversed, every node comes after all of its descendants, which is the
     * closing order.
     *
     * @return list<array{task: Task, depth: int}>
     */
    public function collect(Task $root): array
    {
        $nodes = [];
        $this->walk($root, 1, $nodes, [$root->id => true]);

        return $nodes;
    }

    /**
     * @param  list<array{task: Task, depth: int}>  $nodes
     * @param  array<int, true>  $visited  guards against a corrupted (cyclic) parent chain
     */
    private function walk(Task $parent, int $depth, array &$nodes, array $visited): void
    {
        $children = TaskActionAvailability::whereOpen($parent->subtasks()->getQuery())
            ->with(['taskStatus', 'taskType'])
            ->get();

        foreach ($children as $child) {
            if (isset($visited[$child->id])) {
                continue;
            }

            $nodes[] = ['task' => $child, 'depth' => $depth];
            $this->walk($child, $depth + 1, $nodes, $visited + [$child->id => true]);
        }
    }
}
