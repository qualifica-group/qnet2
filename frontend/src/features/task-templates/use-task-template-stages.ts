import { useRef, useState } from 'react'
import type { TaskTemplateStageFormRow, TaskTemplateStageRowPatch } from '@/features/task-templates/types'

interface UseTaskTemplateStagesArgs {
  initialStageRows: TaskTemplateStageFormRow[]
  /** Fires right after a stage row is removed, so the caller can null out its items' `stage_key` (spec 0146 D-2). */
  onStageRemoved: (removedStageId: string) => void
}

/**
 * Owns the "Fasi" local editor state for `<TaskTemplateStagesEditor>` (spec
 * 0146 D-2): add/rename/remove/reorder, no RHF field array — mirrors
 * `useTaskTemplateForm`'s own `itemRows` state, split out into its own hook
 * because it is a genuinely separate concern (a stage row, not an item row)
 * with its own id namespace and its own removal side-effect.
 */
export function useTaskTemplateStages({ initialStageRows, onStageRemoved }: UseTaskTemplateStagesArgs) {
  const [stageRows, setStageRows] = useState<TaskTemplateStageFormRow[]>(initialStageRows)
  const nextStageRowId = useRef(0)

  const addStageRow = () => {
    nextStageRowId.current += 1
    // Built HERE, synchronously — mirrors `useTaskTemplateForm.addItemRow`:
    // two calls batched into the same commit must not mint the same id.
    const newStage: TaskTemplateStageFormRow = { id: `new-stage-${nextStageRowId.current}`, name: '' }
    setStageRows((rows) => [...rows, newStage])
  }

  const updateStageRow = (id: string, patch: TaskTemplateStageRowPatch) => {
    setStageRows((rows) => rows.map((row) => (row.id === id ? { ...row, ...patch } : row)))
  }

  const removeStageRow = (id: string) => {
    setStageRows((rows) => rows.filter((row) => row.id !== id))
    onStageRemoved(id)
  }

  const reorderStageRows = (orderedIds: string[]) => {
    setStageRows((rows) => {
      const byId = new Map(rows.map((row) => [row.id, row]))
      return orderedIds
        .map((id) => byId.get(id))
        .filter((row): row is TaskTemplateStageFormRow => row !== undefined)
    })
  }

  return { stageRows, addStageRow, updateStageRow, removeStageRow, reorderStageRows }
}
