import { useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import axios from 'axios'
import { isPayloadTooLargeError } from '@/components/rich-text/rich-text-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTaskTemplate, updateTaskTemplate } from '@/features/task-templates/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-templates/task-template-form-payload'
import {
  itemRowsFromDetail,
  newEmptyItemRow,
  stageRowsFromDetail,
  uploadStagedRowAttachments,
} from '@/features/task-templates/task-template-form-hydration'
import {
  buildCreateTaskTemplateSchema,
  buildUpdateTaskTemplateSchema,
  type CreateTaskTemplateFormValues,
} from '@/features/task-templates/task-template-schema'
import {
  extractItemServerErrors,
  validateTaskTemplateItemRows,
} from '@/features/task-templates/task-template-item-validation'
import { moveItemRowToStage } from '@/features/task-templates/task-template-item-stage-grouping'
import {
  extractStageServerErrors,
  validateTaskTemplateStageRows,
} from '@/features/task-templates/task-template-stage-validation'
import { useTaskTemplateStages } from '@/features/task-templates/use-task-template-stages'
import type {
  TaskTemplateDetail,
  TaskTemplateFormMode,
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
  TaskTemplateStageErrors,
} from '@/features/task-templates/types'

/** Server-side field names mapped onto the form for 422 handling. `items`/`stages` are never an RHF path (local state, D-1/D-2) — their server errors go through `extractItemServerErrors`/`extractStageServerErrors` instead. */
const SERVER_ERROR_FIELDS = ['name', 'description'] as const

export type TaskTemplateFormValues = CreateTaskTemplateFormValues

interface UseTaskTemplateFormArgs {
  mode: TaskTemplateFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskTemplate: TaskTemplateDetail) => void
}

/**
 * Owns every non-render concern of `TaskTemplateFormBody`: RHF/Zod wiring for
 * the header, the `items`/`stages` local editor state (`<TaskTemplateStagesEditor>`-
 * driven, not an RHF field array — mirrors `useQuoteWorkflowForm`'s
 * `statusRows`), the per-row attachment staging for not-yet-persisted rows
 * (D-9), and the create/update submit.
 */
export function useTaskTemplateForm({ mode, onSuccess }: UseTaskTemplateFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const [itemsError, setItemsError] = useState<string | null>(null)
  const [itemErrors, setItemErrors] = useState<TaskTemplateItemErrors>({})
  const [stagesError, setStagesError] = useState<string | null>(null)
  const [stageErrors, setStageErrors] = useState<TaskTemplateStageErrors>({})

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateTaskTemplateSchema(t) : buildCreateTaskTemplateSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<TaskTemplateFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.taskTemplate.name,
        description: mode.taskTemplate.description,
        is_active: mode.taskTemplate.is_active,
      }
    }
    return { name: '', description: null, is_active: true }
  }, [mode])

  const form = useForm<TaskTemplateFormValues>({ resolver: zodResolver(schema), defaultValues })

  const [itemRows, setItemRows] = useState<TaskTemplateItemFormRow[]>(() =>
    mode.type === 'edit' ? itemRowsFromDetail(mode.taskTemplate) : [],
  )

  // Spec 0146 D-2: removing a stage falls its items back to "Senza fase" —
  // the SAME rule `TaskTemplateStageWriter::sync` enforces server-side.
  const { stageRows, addStageRow, updateStageRow, removeStageRow, reorderStageRows } = useTaskTemplateStages({
    initialStageRows: mode.type === 'edit' ? stageRowsFromDetail(mode.taskTemplate) : [],
    onStageRemoved: (removedStageId) => {
      setItemRows((rows) => rows.map((row) => (row.stage_key === removedStageId ? { ...row, stage_key: null } : row)))
    },
  })

  const renameStageRow = (id: string, name: string) => updateStageRow(id, { name })

  /** Moves one item row to `targetContainerId` (a stage's own `id`, or the "Senza fase" sentinel) at `targetIndex` — from a pointer drop or the row's "Fase" select (AC-031). */
  const moveItemRow = (rowId: string, targetContainerId: string, targetIndex: number) => {
    setItemRows((rows) => moveItemRowToStage(rows, stageRows, rowId, targetContainerId, targetIndex))
  }

  /**
   * In-memory files staged per NEW row (no `itemId` yet), keyed by the row's
   * local `id` — uploaded only after that row gets a real id from the create
   * response (D-9). A row already persisted uploads/deletes directly through
   * `<DocumentsSection>` and never touches this state.
   */
  const [stagedFilesByRow, setStagedFilesByRow] = useState<Record<string, File[]>>({})

  const nextRowId = useRef(0)

  const addItemRow = () => {
    nextRowId.current += 1
    // The new row is built HERE, synchronously, not inside the `setItemRows`
    // updater: two `addItemRow()` calls batched into the same commit would
    // otherwise both read `nextRowId.current` at FLUSH time (by then already
    // incremented twice) and mint the same id for both rows.
    const newRow = newEmptyItemRow(`new-${nextRowId.current}`)
    setItemRows((rows) => [...rows, newRow])
  }

  const removeItemRow = (id: string) => {
    setItemRows((rows) => rows.filter((row) => row.id !== id))
    setStagedFilesByRow((current) =>
      id in current ? Object.fromEntries(Object.entries(current).filter(([rowId]) => rowId !== id)) : current,
    )
    setItemErrors((current) =>
      id in current ? Object.fromEntries(Object.entries(current).filter(([rowId]) => rowId !== id)) : current,
    )
  }

  const updateItemRow = (id: string, patch: TaskTemplateItemRowPatch) => {
    setItemRows((rows) => rows.map((row) => (row.id === id ? { ...row, ...patch } : row)))
  }

  const addStagedRowFiles = (rowId: string, files: File[]) => {
    setStagedFilesByRow((current) => ({ ...current, [rowId]: [...(current[rowId] ?? []), ...files] }))
  }

  const removeStagedRowFile = (rowId: string, index: number) => {
    setStagedFilesByRow((current) => ({
      ...current,
      [rowId]: (current[rowId] ?? []).filter((_file, fileIndex) => fileIndex !== index),
    }))
  }

  const onSubmit = async (values: TaskTemplateFormValues) => {
    setServerError(null)

    // Step 1: validate the local rows client-side (title/due offset/estimate
    // ranges, row count; stage names) before ever hitting the network.
    const itemValidation = validateTaskTemplateItemRows(itemRows, t)
    setItemErrors(itemValidation.errors)
    setItemsError(itemValidation.formError)
    const stageValidation = validateTaskTemplateStageRows(stageRows, t)
    setStageErrors(stageValidation.errors)
    setStagesError(stageValidation.formError)
    if (itemValidation.formError !== null || stageValidation.formError !== null) {
      return
    }

    const errorFields: Path<TaskTemplateFormValues>[] = [...SERVER_ERROR_FIELDS]
    const orderedRowIds = itemRows.map((row) => row.id)
    const orderedStageIds = stageRows.map((row) => row.id)

    try {
      if (mode.type === 'edit') {
        const saved = await updateTaskTemplate(
          mode.taskTemplate.id,
          buildUpdatePayload(values, itemRows, stageRows, mode.taskTemplate),
        )
        queryClient.setQueryData(['task-templates', 'detail', mode.taskTemplate.id], saved)
        toast.success(t('taskTemplates.form.updated'))
        onSuccess(saved)
        return
      }

      // Step 2 (create): POST the template with every row and every stage in visual order.
      const created = await createTaskTemplate(buildCreatePayload(values, itemRows, stageRows))

      // Step 3: upload each row's staged files against the id the server
      // assigned it, matched positionally — same order sent, same order
      // returned (D-9).
      const failedFiles: string[] = []
      for (let index = 0; index < orderedRowIds.length; index += 1) {
        const files = stagedFilesByRow[orderedRowIds[index]]
        const createdItem = created.items[index]
        if (!files || files.length === 0 || !createdItem) {
          continue
        }
        failedFiles.push(...(await uploadStagedRowAttachments(createdItem.id, files)))
      }
      if (failedFiles.length > 0) {
        toast.error(t('taskTemplates.form.items.attachmentsUploadFailed', { files: failedFiles.join(', ') }))
      }

      toast.success(t('taskTemplates.form.created'))
      onSuccess(created)
    } catch (error) {
      // Spec 0128 follow-up: the header AND every row description can carry
      // inline `data:` images, so a 413 cannot be attributed to a single
      // field — shown as the form's own generic error, same surface every
      // other non-field failure already uses below. Values are untouched.
      if (isPayloadTooLargeError(error)) {
        setServerError(t('richText.errors.payloadTooLarge'))
        return
      }

      const appliedField = applyServerValidationErrors(error, form.setError, errorFields)

      let appliedRows = false
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const serverItemErrors = extractItemServerErrors(error.response.data?.errors, orderedRowIds)
        if (Object.keys(serverItemErrors).length > 0) {
          setItemErrors((current) => ({ ...current, ...serverItemErrors }))
          setItemsError(t('taskTemplates.form.items.hasErrors'))
          appliedRows = true
        }
        const serverStageErrors = extractStageServerErrors(error.response.data?.errors, orderedStageIds)
        if (Object.keys(serverStageErrors).length > 0) {
          setStageErrors((current) => ({ ...current, ...serverStageErrors }))
          setStagesError(t('taskTemplates.form.items.hasErrors'))
          appliedRows = true
        }
      }

      if (!appliedField && !appliedRows) {
        setServerError(t('taskTemplates.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    itemsError,
    itemErrors,
    itemRows,
    addItemRow,
    removeItemRow,
    updateItemRow,
    moveItemRow,
    stagedFilesByRow,
    addStagedRowFiles,
    removeStagedRowFile,
    stageRows,
    stagesError,
    stageErrors,
    addStageRow,
    renameStageRow,
    removeStageRow,
    reorderStageRows,
    onSubmit,
  }
}
