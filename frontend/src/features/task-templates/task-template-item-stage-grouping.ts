import type { TaskTemplateItemFormRow, TaskTemplateStageFormRow } from '@/features/task-templates/types'

/**
 * Sentinel container id for the "Senza fase" pseudo-group (spec 0146 D-2):
 * never collides with a real stage row's own `id`, which is always prefixed
 * `stage-`/`new-stage-` (`use-task-template-stages.ts`).
 */
export const UNASSIGNED_STAGE_CONTAINER_ID = '__no_stage__'

/**
 * One row inside a stage group's display order, with its nesting `depth`
 * (spec 0172 D-1: 0 = root, up to `MAX_ITEM_DEPTH`). A sub-item's OWN
 * `stage_key` is always `null` (D-3) — it inherits the fase of the root it
 * sits under, which is why grouping (below) tracks it, not the row itself.
 */
export interface TaskTemplateStageGroupEntry {
  row: TaskTemplateItemFormRow
  depth: number
}

/** One drag-board column: a real stage (`stage !== null`) or the trailing "Senza fase" group. */
export interface TaskTemplateStageGroup {
  containerId: string
  stage: TaskTemplateStageFormRow | null
  /** Root rows only (depth 0) — the set the board's `SortableContext` drags, each carrying its own subtree along. */
  rootRows: TaskTemplateItemFormRow[]
  /** Every row of the group, root-first-then-its-subtree, in display order. */
  entries: TaskTemplateStageGroupEntry[]
}

function containerIdOf(stageKey: string | null): string {
  return stageKey ?? UNASSIGNED_STAGE_CONTAINER_ID
}

/**
 * Groups the flat `itemRows` by their EFFECTIVE stage: a root row groups by
 * its own `stage_key`, a sub-item (whose `stage_key` is always `null`,
 * spec 0172 D-3) follows the group of the root it currently sits under. This
 * walk relies on `itemRows` staying depth-first ordered (each root
 * immediately followed by its full subtree, data_contract's own
 * "padre sempre prima dei figli") — the SAME invariant `flattenStageGroups`
 * below reconstructs, and every row-mutating operation in this module and
 * `use-task-template-form.ts` preserves.
 */
export function groupItemRowsByStage(
  itemRows: TaskTemplateItemFormRow[],
  stageRows: TaskTemplateStageFormRow[],
): TaskTemplateStageGroup[] {
  const groups = stageRows.map(
    (stage): TaskTemplateStageGroup => ({ containerId: stage.id, stage, rootRows: [], entries: [] }),
  )
  const unassigned: TaskTemplateStageGroup = {
    containerId: UNASSIGNED_STAGE_CONTAINER_ID,
    stage: null,
    rootRows: [],
    entries: [],
  }
  const byContainerId = new Map(groups.map((group) => [group.containerId, group]))

  let currentGroup = unassigned
  const depthById = new Map<string, number>()

  for (const row of itemRows) {
    let depth: number
    if (row.parent_key === null) {
      currentGroup = byContainerId.get(containerIdOf(row.stage_key)) ?? unassigned
      currentGroup.rootRows.push(row)
      depth = 0
    } else {
      depth = (depthById.get(row.parent_key) ?? 0) + 1
    }
    depthById.set(row.id, depth)
    currentGroup.entries.push({ row, depth })
  }

  return [...groups, unassigned]
}

/** Rebuilds the flat `itemRows` order from its grouped view: stage order first, "Senza fase" last, each root's subtree kept together. */
export function flattenStageGroups(groups: TaskTemplateStageGroup[]): TaskTemplateItemFormRow[] {
  return groups.flatMap((group) => group.entries.map((entry) => entry.row))
}

/**
 * Moves one ROOT row — and its whole subtree along with it — to
 * `targetContainerId` at `targetRootIndex` (an index among the target
 * group's roots) — from a pointer drop, or from the "Fase" select's
 * keyboard-accessible move (AC-031). Sub-items are never draggable on their
 * own (spec 0172, out of scope): only `rowId`s that are currently a root are
 * resolved. Returns the SAME array reference when the row or the target
 * container cannot be resolved, so callers can skip a state update.
 */
export function moveItemRowToStage(
  itemRows: TaskTemplateItemFormRow[],
  stageRows: TaskTemplateStageFormRow[],
  rowId: string,
  targetContainerId: string,
  targetRootIndex: number,
): TaskTemplateItemFormRow[] {
  const groups = groupItemRowsByStage(itemRows, stageRows)
  const sourceGroup = groups.find((group) => group.rootRows.some((row) => row.id === rowId))
  const targetGroup = groups.find((group) => group.containerId === targetContainerId)
  if (!sourceGroup || !targetGroup) {
    return itemRows
  }

  // Step 1: pull the root's own entry PLUS every entry of its subtree
  // (the contiguous run of deeper entries right after it) out of the source group.
  const startIndex = sourceGroup.entries.findIndex((entry) => entry.row.id === rowId)
  let endIndex = startIndex + 1
  while (endIndex < sourceGroup.entries.length && sourceGroup.entries[endIndex].depth > 0) {
    endIndex += 1
  }
  const block = sourceGroup.entries.splice(startIndex, endIndex - startIndex)
  sourceGroup.rootRows = sourceGroup.rootRows.filter((row) => row.id !== rowId)

  // Step 2: retag only the root's own `stage_key` — its descendants never carry one (D-3).
  const targetStageKey = targetGroup.stage?.id ?? null
  const [rootEntry, ...descendantEntries] = block
  const movedRoot = rootEntry.row.stage_key === targetStageKey ? rootEntry.row : { ...rootEntry.row, stage_key: targetStageKey }
  const movedBlock = [{ ...rootEntry, row: movedRoot }, ...descendantEntries]

  // Step 3: insert the block before the target group's root currently at `targetRootIndex` (end of group past the last root).
  const rootEntryIndexes = targetGroup.entries.reduce<number[]>(
    (indexes, entry, index) => (entry.depth === 0 ? [...indexes, index] : indexes),
    [],
  )
  const insertAt = rootEntryIndexes[targetRootIndex] ?? targetGroup.entries.length
  targetGroup.entries.splice(insertAt, 0, ...movedBlock)
  const targetRootInsertAt = Math.min(Math.max(targetRootIndex, 0), targetGroup.rootRows.length)
  targetGroup.rootRows.splice(targetRootInsertAt, 0, movedRoot)

  return flattenStageGroups(groups)
}
