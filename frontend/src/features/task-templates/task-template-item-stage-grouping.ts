import type { TaskTemplateItemFormRow, TaskTemplateStageFormRow } from '@/features/task-templates/types'

/**
 * Sentinel container id for the "Senza fase" pseudo-group (spec 0146 D-2):
 * never collides with a real stage row's own `id`, which is always prefixed
 * `stage-`/`new-stage-` (`use-task-template-stages.ts`).
 */
export const UNASSIGNED_STAGE_CONTAINER_ID = '__no_stage__'

/** One drag-board column: a real stage (`stage !== null`) or the trailing "Senza fase" group. */
export interface TaskTemplateStageGroup {
  containerId: string
  stage: TaskTemplateStageFormRow | null
  rows: TaskTemplateItemFormRow[]
}

function containerIdOf(stageKey: string | null): string {
  return stageKey ?? UNASSIGNED_STAGE_CONTAINER_ID
}

/**
 * Groups the flat `itemRows` by their `stage_key`, one group per stage row
 * IN STAGE ORDER, plus a trailing "Senza fase" group — the same ordering
 * `flattenStageGroups` reconstructs the wire order from (the backend's own
 * `sort_order` is the index in that flat array, spec 0146 data_contract).
 */
export function groupItemRowsByStage(
  itemRows: TaskTemplateItemFormRow[],
  stageRows: TaskTemplateStageFormRow[],
): TaskTemplateStageGroup[] {
  const groups = stageRows.map((stage): TaskTemplateStageGroup => ({ containerId: stage.id, stage, rows: [] }))
  const unassigned: TaskTemplateStageGroup = { containerId: UNASSIGNED_STAGE_CONTAINER_ID, stage: null, rows: [] }
  const byContainerId = new Map(groups.map((group) => [group.containerId, group]))

  for (const row of itemRows) {
    const group = byContainerId.get(containerIdOf(row.stage_key)) ?? unassigned
    group.rows.push(row)
  }

  return [...groups, unassigned]
}

/** Rebuilds the flat `itemRows` order from its grouped view: stage order first, "Senza fase" last. */
export function flattenStageGroups(groups: TaskTemplateStageGroup[]): TaskTemplateItemFormRow[] {
  return groups.flatMap((group) => group.rows)
}

/**
 * Moves one row to `targetContainerId` at `targetIndex` — from a pointer
 * drop, or from the "Fase" select's keyboard-accessible move (AC-031) —
 * retagging its `stage_key` to match, and returns the full flat `itemRows`
 * in the new order. Returns the SAME array reference when the row or the
 * target container cannot be resolved, so callers can skip a state update.
 */
export function moveItemRowToStage(
  itemRows: TaskTemplateItemFormRow[],
  stageRows: TaskTemplateStageFormRow[],
  rowId: string,
  targetContainerId: string,
  targetIndex: number,
): TaskTemplateItemFormRow[] {
  const groups = groupItemRowsByStage(itemRows, stageRows)
  const sourceGroup = groups.find((group) => group.rows.some((row) => row.id === rowId))
  const targetGroup = groups.find((group) => group.containerId === targetContainerId)
  if (!sourceGroup || !targetGroup) {
    return itemRows
  }

  const sourceIndex = sourceGroup.rows.findIndex((row) => row.id === rowId)
  const [row] = sourceGroup.rows.splice(sourceIndex, 1)
  const targetStageKey = targetGroup.stage?.id ?? null
  const movedRow = row.stage_key === targetStageKey ? row : { ...row, stage_key: targetStageKey }
  const clampedIndex = Math.min(Math.max(targetIndex, 0), targetGroup.rows.length)
  targetGroup.rows.splice(clampedIndex, 0, movedRow)

  return flattenStageGroups(groups)
}
