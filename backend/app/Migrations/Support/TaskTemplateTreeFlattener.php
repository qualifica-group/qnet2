<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\DataObjects\TaskTemplates\TaskTemplateStageData;
use App\RichText\RichTextConverter;

/**
 * Flattens ONE legacy `task-templates` record's `stages[].items[]` (raw
 * `templatebtypemilestoneactions`, self-referencing `parent_id`, no depth
 * cap) into the qnet-2 `CreateTaskTemplateData` shape (spec 0172): ordered
 * `TaskTemplateStageData[]` + a flat, parent-before-children
 * `TaskTemplateItemData[]` carrying request-scoped `key`/`parentKey` (D-2).
 *
 * A sub-action is kept nested ONLY when its parent exists inside THIS SAME
 * record, is not part of a cycle (self-reference included) and the
 * resulting chain sits at most 3 levels below a root (D-1); any other case
 * — unresolved/foreign parent, cycle, depth > 3 — promotes the action to a
 * root item of ITS OWN legacy stage, with a warning citing its id and the
 * offending `parent_id` (D-9). A cycle longer than a self-reference is
 * broken by promoting whichever node is reached FIRST while walking back up
 * an already-visiting chain; the other member keeps its real parent — a
 * deterministic, order-dependent choice, not a per-node guarantee (the
 * legacy stats behind spec 0172 do not distinguish the two shapes).
 *
 * A sub-action never carries its own stage (D-3): it follows its ROOT's
 * stage regardless of depth, but a mismatch between its own legacy stage
 * and its root's is still reported as a warning.
 *
 * A blank title drops the action entirely (warning) rather than importing
 * an empty row; any of ITS OWN children then find no parent in the record
 * and are promoted to root by the same rule above.
 */
final class TaskTemplateTreeFlattener
{
    // Mirrors Http\Requests\TaskTemplates\Concerns\ValidatesTaskTemplateItems
    // (private there): the source enforces the same invariants the
    // FormRequest normally would, since TaskTemplateService::create() never
    // runs that validation (spec 0172 M4).
    private const int TITLE_MAX = 191;

    private const int STAGE_NAME_MAX = 191;

    // D-1: up to 3 levels below a root (child, grandchild, great-grandchild).
    private const int MAX_SUB_LEVELS = 3;

    /** @var array<int, array<string, mixed>> raw item keyed by external id, each carrying 'home_stage_id' */
    private array $itemsById = [];

    /** @var array<int, int|null> resolved parent id in the OUTPUT tree, null = root (including a promoted anomaly) */
    private array $resolvedParentId = [];

    /** @var array<int, int> resolved depth, 0 = root */
    private array $depthCache = [];

    /** @var array<int, true> recursion guard for cycle detection */
    private array $visiting = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /**
     * @param  array<int, array<string, mixed>>  $rawStages  this record's `stages[]`, unordered
     */
    public function __construct(private readonly array $rawStages) {}

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array{stages: array<int, TaskTemplateStageData>, items: array<int, TaskTemplateItemData>}
     */
    public function flatten(): array
    {
        // Step 1: order stages by position (null last) then id, mint their keys.
        $orderedStages = $this->sortByPositionThenId($this->rawStages);
        $stageDtos = array_map(fn (array $stage): TaskTemplateStageData => $this->buildStageData($stage), $orderedStages);

        // Step 2: flatten every stage's items into one id-indexed map (their home stage remembered), dropping blank titles.
        $this->indexItems($orderedStages);

        // Step 3: resolve every item's depth/parent up front, so a later child lookup never sees a half-resolved sibling.
        foreach (array_keys($this->itemsById) as $itemId) {
            $this->depthOf($itemId);
        }

        // Step 4: per stage, its roots ordered by (position, id), each followed depth-first by its resolved subtree.
        $items = [];

        foreach ($orderedStages as $stage) {
            $stageId = (int) $stage['id'];

            foreach ($this->rootsOf($stageId) as $root) {
                $this->appendSubtree($items, $root, null, 's'.$stageId, $stageId);
            }
        }

        return ['stages' => $stageDtos, 'items' => $items];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortByPositionThenId(array $rows): array
    {
        $rows = array_values($rows);

        usort($rows, static function (array $a, array $b): int {
            $positionA = $a['position'] ?? null;
            $positionB = $b['position'] ?? null;

            if ($positionA === $positionB) {
                return ((int) $a['id']) <=> ((int) $b['id']);
            }

            if ($positionA === null) {
                return 1;
            }

            if ($positionB === null) {
                return -1;
            }

            return $positionA <=> $positionB;
        });

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function buildStageData(array $stage): TaskTemplateStageData
    {
        $stageId = (int) $stage['id'];
        $name = trim((string) ($stage['name'] ?? ''));

        if (mb_strlen($name) > self::STAGE_NAME_MAX) {
            $this->warnings[] = 'Stage name truncated to '.self::STAGE_NAME_MAX." characters (external id {$stageId}).";
            $name = mb_substr($name, 0, self::STAGE_NAME_MAX);
        }

        return new TaskTemplateStageData(id: null, key: 's'.$stageId, name: $name);
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderedStages
     */
    private function indexItems(array $orderedStages): void
    {
        foreach ($orderedStages as $stage) {
            $stageId = (int) $stage['id'];

            foreach ((array) ($stage['items'] ?? []) as $rawItem) {
                if (! isset($rawItem['id'])) {
                    continue;
                }

                $itemId = (int) $rawItem['id'];
                $title = trim((string) ($rawItem['title'] ?? ''));

                if ($title === '') {
                    $this->warnings[] = "Action without a title (external id {$itemId}); action skipped.";

                    continue;
                }

                $rawItem['home_stage_id'] = $stageId;
                $rawItem['title'] = $title;
                $this->itemsById[$itemId] = $rawItem;
            }
        }
    }

    /**
     * Resolves (and memoizes) $itemId's depth in the OUTPUT tree: 0 for a
     * root, INCLUDING every promoted anomaly (D-9, see class docblock), or
     * its parent's depth + 1 otherwise.
     */
    private function depthOf(int $itemId): int
    {
        if (isset($this->depthCache[$itemId])) {
            return $this->depthCache[$itemId];
        }

        $rawParentId = $this->itemsById[$itemId]['parent_id'] ?? null;
        $rawParentId = ($rawParentId === null || (int) $rawParentId === 0) ? null : (int) $rawParentId;

        if ($rawParentId === null) {
            $this->resolvedParentId[$itemId] = null;

            return $this->depthCache[$itemId] = 0;
        }

        if ($rawParentId === $itemId) {
            return $this->promoteToRoot($itemId, $rawParentId, 'self-referencing parent_id');
        }

        if (! isset($this->itemsById[$rawParentId])) {
            return $this->promoteToRoot($itemId, $rawParentId, 'parent not found in this model');
        }

        if (isset($this->visiting[$rawParentId])) {
            return $this->promoteToRoot($itemId, $rawParentId, 'cyclic parent chain');
        }

        $this->visiting[$itemId] = true;
        $parentDepth = $this->depthOf($rawParentId);
        unset($this->visiting[$itemId]);

        if ($parentDepth + 1 > self::MAX_SUB_LEVELS) {
            return $this->promoteToRoot($itemId, $rawParentId, 'nesting deeper than '.self::MAX_SUB_LEVELS.' levels');
        }

        $this->resolvedParentId[$itemId] = $rawParentId;

        return $this->depthCache[$itemId] = $parentDepth + 1;
    }

    private function promoteToRoot(int $itemId, int $rawParentId, string $reason): int
    {
        $this->resolvedParentId[$itemId] = null;
        $this->warnings[] = "Action {$itemId} promoted to a root item of its own stage: {$reason} (parent_id {$rawParentId}).";

        return $this->depthCache[$itemId] = 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rootsOf(int $stageId): array
    {
        $roots = array_values(array_filter(
            $this->itemsById,
            fn (array $item): bool => $this->resolvedParentId[(int) $item['id']] === null && (int) $item['home_stage_id'] === $stageId,
        ));

        usort($roots, fn (array $a, array $b): int => $this->comparePosition($a, $b));

        return $roots;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function childrenOf(int $itemId): array
    {
        $children = array_values(array_filter(
            $this->itemsById,
            fn (array $item): bool => $this->resolvedParentId[(int) $item['id']] === $itemId,
        ));

        usort($children, fn (array $a, array $b): int => $this->comparePosition($a, $b));

        return $children;
    }

    /**
     * @param  array<int, TaskTemplateItemData>  $items
     * @param  array<string, mixed>  $rawItem
     */
    private function appendSubtree(array &$items, array $rawItem, ?string $parentKey, string $rootStageKey, int $rootStageId): void
    {
        $itemId = (int) $rawItem['id'];
        $isRoot = $parentKey === null;

        if (! $isRoot && (int) $rawItem['home_stage_id'] !== $rootStageId) {
            $this->warnings[] = "Sub-action {$itemId} belongs to a different legacy stage than its root; it follows the root's stage instead.";
        }

        $key = 'i'.$itemId;

        $items[] = new TaskTemplateItemData(
            id: null,
            title: $this->truncatedTitle($rawItem),
            description: RichTextConverter::plainTextToHtml((string) ($rawItem['description'] ?? ''), convertMentionTokens: false),
            estimatedMinutes: $this->estimatedMinutes($rawItem),
            taskStatusId: null,
            dueOffsetDays: 0,
            stageKey: $isRoot ? $rootStageKey : null,
            key: $key,
            parentKey: $parentKey,
        );

        foreach ($this->childrenOf($itemId) as $child) {
            $this->appendSubtree($items, $child, $key, $rootStageKey, $rootStageId);
        }
    }

    /**
     * @param  array<string, mixed>  $rawItem
     */
    private function truncatedTitle(array $rawItem): string
    {
        $title = (string) $rawItem['title'];

        if (mb_strlen($title) > self::TITLE_MAX) {
            $this->warnings[] = 'Action title truncated to '.self::TITLE_MAX." characters (external id {$rawItem['id']}).";

            return mb_substr($title, 0, self::TITLE_MAX);
        }

        return $title;
    }

    /**
     * @param  array<string, mixed>  $rawItem
     */
    private function estimatedMinutes(array $rawItem): ?int
    {
        $hours = $rawItem['estimated_hours'] ?? null;
        $minutes = $rawItem['estimated_minutes'] ?? null;

        if ($hours === null && $minutes === null) {
            return null;
        }

        return ((int) ($hours ?? 0)) * 60 + (int) ($minutes ?? 0);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function comparePosition(array $a, array $b): int
    {
        $positionA = (int) ($a['position'] ?? 0);
        $positionB = (int) ($b['position'] ?? 0);

        if ($positionA === $positionB) {
            return ((int) $a['id']) <=> ((int) $b['id']);
        }

        return $positionA <=> $positionB;
    }
}
