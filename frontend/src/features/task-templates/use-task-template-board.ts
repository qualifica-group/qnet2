import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import axios from 'axios'
import {
  itemRowsFromDetail,
  newEmptyItemRow,
  newEmptySubtaskItemRow,
  stageRowsFromDetail,
  uploadStagedRowAttachments,
} from '@/features/task-templates/task-template-form-hydration'
import { FORM_ROW_TREE_ACCESSORS, getSubtreeIds } from '@/features/task-templates/task-template-item-tree'
import {
  extractItemServerErrors,
  validateTaskTemplateItemRows,
} from '@/features/task-templates/task-template-item-validation'
import { moveItemRowToStage } from '@/features/task-templates/task-template-item-stage-grouping'
import {
  extractStageServerErrors,
  validateTaskTemplateStageRows,
} from '@/features/task-templates/task-template-stage-validation'
import type { TaskTemplateStagesEditorProps } from '@/features/task-templates/task-template-stages-editor'
import { useTaskTemplateStages } from '@/features/task-templates/use-task-template-stages'
import type {
  TaskTemplateDetail,
  TaskTemplateItem,
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
  TaskTemplateStageErrors,
} from '@/features/task-templates/types'

/**
 * The "Fasi e attivita'" board of a task template (spec 0124/0146/0172): the
 * item and stage rows as local state (a drag board, not an RHF field array),
 * the files staged on rows not saved yet (D-9), their client validation and
 * the mapping of a 422 back onto them. Shared by the create form (the board
 * always open) and the detail's in-place board row (spec 0227 D-3).
 */
export function useTaskTemplateBoard(initial: TaskTemplateDetail | null) {
  const { t } = useTranslation()
  const [itemRows, setItemRows] = useState<TaskTemplateItemFormRow[]>(() => (initial ? itemRowsFromDetail(initial) : []))
  const [itemsError, setItemsError] = useState<string | null>(null)
  const [itemErrors, setItemErrors] = useState<TaskTemplateItemErrors>({})
  const [stagesError, setStagesError] = useState<string | null>(null)
  const [stageErrors, setStageErrors] = useState<TaskTemplateStageErrors>({})

  // Spec 0146 D-2: removing a stage falls its items back to "Senza fase" —
  // the SAME rule `TaskTemplateStageWriter::sync` enforces server-side.
  const { stageRows, setStageRows, addStageRow, updateStageRow, removeStageRow, reorderStageRows } =
    useTaskTemplateStages({
      initialStageRows: initial ? stageRowsFromDetail(initial) : [],
      onStageRemoved: (removedStageId) => {
        setItemRows((rows) => rows.map((row) => (row.stage_key === removedStageId ? { ...row, stage_key: null } : row)))
      },
    })

  /**
   * In-memory files staged per NEW row (no `itemId` yet), keyed by the row's
   * local `id` — uploaded only once that row has a real id from the save
   * response (D-9). A persisted row uploads/deletes directly through
   * `<DocumentsSection>` and never touches this state.
   */
  const [stagedFilesByRow, setStagedFilesByRow] = useState<Record<string, File[]>>({})

  const nextRowId = useRef(0)

  const clearErrors = () => {
    setItemErrors({})
    setItemsError(null)
    setStageErrors({})
    setStagesError(null)
  }

  /** Puts the board back on `detail` (or empty), dropping staged files and errors: a fresh edit session. */
  const reset = (detail: TaskTemplateDetail | null) => {
    setItemRows(detail ? itemRowsFromDetail(detail) : [])
    setStageRows(detail ? stageRowsFromDetail(detail) : [])
    setStagedFilesByRow({})
    clearErrors()
  }

  const addItemRow = () => {
    nextRowId.current += 1
    // Built HERE, synchronously, not inside the `setItemRows` updater: two
    // calls batched into the same commit would otherwise both read
    // `nextRowId.current` at FLUSH time and mint the same id.
    const newRow = newEmptyItemRow(`new-${nextRowId.current}`)
    setItemRows((rows) => [...rows, newRow])
  }

  /** Appends a sub-task under `parentId`, right after its last descendant: rows stay depth-first ordered (spec 0172 D-1). */
  const addSubtaskItemRow = (parentId: string) => {
    nextRowId.current += 1
    const newRowId = `new-${nextRowId.current}`
    setItemRows((rows) => {
      const parentRow = rows.find((row) => row.id === parentId)
      if (!parentRow) {
        return rows
      }
      const newRow = newEmptySubtaskItemRow(newRowId, parentRow)
      const subtreeIds = getSubtreeIds(rows, FORM_ROW_TREE_ACCESSORS, parentId)
      const insertAt = rows.findIndex((row) => row.id === subtreeIds[subtreeIds.length - 1]) + 1
      return [...rows.slice(0, insertAt), newRow, ...rows.slice(insertAt)]
    })
  }

  /** Removing a row removes its WHOLE subtree (spec 0172 AC-018), each row's own staged files and errors along with it. */
  const removeItemRow = (id: string) => {
    const idsToRemove = new Set(getSubtreeIds(itemRows, FORM_ROW_TREE_ACCESSORS, id))
    setItemRows((rows) => rows.filter((row) => !idsToRemove.has(row.id)))
    setStagedFilesByRow((current) =>
      Object.fromEntries(Object.entries(current).filter(([rowId]) => !idsToRemove.has(rowId))),
    )
    setItemErrors((current) =>
      Object.fromEntries(Object.entries(current).filter(([rowId]) => !idsToRemove.has(rowId))),
    )
  }

  const updateItemRow = (id: string, patch: TaskTemplateItemRowPatch) => {
    setItemRows((rows) => rows.map((row) => (row.id === id ? { ...row, ...patch } : row)))
  }

  /** Moves one row to a stage container (or "Senza fase") at `targetIndex` — a drop or the row's "Fase" select (AC-031). */
  const moveItemRow = (rowId: string, targetContainerId: string, targetIndex: number) => {
    setItemRows((rows) => moveItemRowToStage(rows, stageRows, rowId, targetContainerId, targetIndex))
  }

  const renameStageRow = (id: string, name: string) => updateStageRow(id, { name })

  const addStagedRowFiles = (rowId: string, files: File[]) => {
    setStagedFilesByRow((current) => ({ ...current, [rowId]: [...(current[rowId] ?? []), ...files] }))
  }

  const removeStagedRowFile = (rowId: string, index: number) => {
    setStagedFilesByRow((current) => ({
      ...current,
      [rowId]: (current[rowId] ?? []).filter((_file, fileIndex) => fileIndex !== index),
    }))
  }

  /** Client validation of rows and stages (titles, ranges, counts, stage names); `true` = the board may be sent. */
  const validate = (): boolean => {
    const itemValidation = validateTaskTemplateItemRows(itemRows, t)
    setItemErrors(itemValidation.errors)
    setItemsError(itemValidation.formError)
    const stageValidation = validateTaskTemplateStageRows(stageRows, t)
    setStageErrors(stageValidation.errors)
    setStagesError(stageValidation.formError)
    return itemValidation.formError === null && stageValidation.formError === null
  }

  /** Maps a 422's `items.N.*`/`stages.N.*` errors onto the rows that were sent; `true` when any landed. */
  const applyServerErrors = (error: unknown, sentItemIds: string[], sentStageIds: string[]): boolean => {
    if (!axios.isAxiosError(error) || error.response?.status !== 422) {
      return false
    }
    let applied = false
    const serverItemErrors = extractItemServerErrors(error.response.data?.errors, sentItemIds)
    if (Object.keys(serverItemErrors).length > 0) {
      setItemErrors((current) => ({ ...current, ...serverItemErrors }))
      setItemsError(t('taskTemplates.form.items.hasErrors'))
      applied = true
    }
    const serverStageErrors = extractStageServerErrors(error.response.data?.errors, sentStageIds)
    if (Object.keys(serverStageErrors).length > 0) {
      setStageErrors((current) => ({ ...current, ...serverStageErrors }))
      setStagesError(t('taskTemplates.form.items.hasErrors'))
      applied = true
    }
    return applied
  }

  /**
   * Uploads each sent row's staged files against the id the server returned
   * at the SAME position (rows are saved in the order sent, D-9); a failure
   * is reported, never thrown, since the template itself is already saved.
   */
  const uploadStagedFiles = async (sentRows: TaskTemplateItemFormRow[], savedItems: TaskTemplateItem[]) => {
    const failedFiles: string[] = []
    for (let index = 0; index < sentRows.length; index += 1) {
      const files = stagedFilesByRow[sentRows[index].id]
      const savedItem = savedItems[index]
      if (!files || files.length === 0 || !savedItem) {
        continue
      }
      failedFiles.push(...(await uploadStagedRowAttachments(savedItem.id, files)))
    }
    if (failedFiles.length > 0) {
      toast.error(t('taskTemplates.form.items.attachmentsUploadFailed', { files: failedFiles.join(', ') }))
    }
  }

  /** Everything `<TaskTemplateStagesEditor>` takes, `disabled` aside. */
  const editorProps: Omit<TaskTemplateStagesEditorProps, 'disabled'> = {
    itemRows,
    stageRows,
    itemErrors,
    stageErrors,
    stagedFilesByRow,
    onAddStage: addStageRow,
    onRenameStage: renameStageRow,
    onRemoveStage: removeStageRow,
    onReorderStages: reorderStageRows,
    onAddItem: addItemRow,
    onUpdateItem: updateItemRow,
    onRemoveItem: removeItemRow,
    onAddSubtaskItem: addSubtaskItemRow,
    onMoveItem: moveItemRow,
    onAddStagedFiles: addStagedRowFiles,
    onRemoveStagedFile: removeStagedRowFile,
  }

  return {
    itemRows,
    stageRows,
    itemsError,
    stagesError,
    itemErrors,
    stageErrors,
    stagedFilesByRow,
    addItemRow,
    addSubtaskItemRow,
    removeItemRow,
    updateItemRow,
    moveItemRow,
    addStageRow,
    renameStageRow,
    removeStageRow,
    reorderStageRows,
    addStagedRowFiles,
    removeStagedRowFile,
    reset,
    validate,
    applyServerErrors,
    uploadStagedFiles,
    editorProps,
  }
}

export type TaskTemplateBoard = ReturnType<typeof useTaskTemplateBoard>
