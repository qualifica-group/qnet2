import { useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import axios from 'axios'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { createTaskTemplate, updateTaskTemplate } from '@/features/task-templates/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-templates/task-template-form-payload'
import {
  buildCreateTaskTemplateSchema,
  buildUpdateTaskTemplateSchema,
  type CreateTaskTemplateFormValues,
} from '@/features/task-templates/task-template-schema'
import {
  extractItemServerErrors,
  validateTaskTemplateItemRows,
} from '@/features/task-templates/task-template-item-validation'
import { TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS } from '@/features/task-templates/types'
import type {
  TaskTemplateDetail,
  TaskTemplateFormMode,
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
} from '@/features/task-templates/types'

/** Server-side field names mapped onto the form for 422 handling. `items` is never an RHF path (local state, D-1) — its server errors go through `extractItemServerErrors` instead. */
const SERVER_ERROR_FIELDS = ['name', 'description'] as const

export type TaskTemplateFormValues = CreateTaskTemplateFormValues

function newEmptyRow(id: string): TaskTemplateItemFormRow {
  return {
    id,
    title: '',
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    due_offset_days: 0,
  }
}

/** Hydrates the editable rows from a persisted template (already ordered by `sort_order`). */
function itemRowsFromDetail(taskTemplate: TaskTemplateDetail): TaskTemplateItemFormRow[] {
  return taskTemplate.items.map((item) => ({
    id: String(item.id),
    itemId: item.id,
    title: item.title,
    description: item.description,
    estimated_minutes: item.estimated_minutes,
    task_status_id: item.task_status_id,
    due_offset_days: item.due_offset_days,
  }))
}

/**
 * Uploads every staged file of a row against the item id the server just
 * assigned it, one request at a time (mirrors `useTaskForm`'s own sequential
 * upload: the endpoint takes one file per request). Never throws: a rejected
 * file is reported back by name so the caller can still succeed the save
 * (the template — and its rows — are already persisted).
 */
async function uploadStagedRowAttachments(itemId: number, files: File[]): Promise<string[]> {
  const failed: string[] = []
  for (const file of files) {
    try {
      await uploadAttachment({
        resource: TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS,
        id: itemId,
        collection: DOCUMENTS_COLLECTION,
        file,
      })
    } catch {
      failed.push(file.name)
    }
  }
  return failed
}

interface UseTaskTemplateFormArgs {
  mode: TaskTemplateFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskTemplate: TaskTemplateDetail) => void
}

/**
 * Owns every non-render concern of `TaskTemplateFormBody`: RHF/Zod wiring for
 * the header, the `items` local editor state (`<SortableList>`-driven, not an
 * RHF field array — mirrors `useQuoteWorkflowForm`'s `statusRows`), the
 * per-row attachment staging for not-yet-persisted rows (D-9), and the
 * create/update submit.
 */
export function useTaskTemplateForm({ mode, onSuccess }: UseTaskTemplateFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const [itemsError, setItemsError] = useState<string | null>(null)
  const [itemErrors, setItemErrors] = useState<TaskTemplateItemErrors>({})

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
    const newRow = newEmptyRow(`new-${nextRowId.current}`)
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

  const reorderItemRows = (orderedIds: string[]) => {
    setItemRows((rows) => {
      const byId = new Map(rows.map((row) => [row.id, row]))
      return orderedIds
        .map((id) => byId.get(id))
        .filter((row): row is TaskTemplateItemFormRow => row !== undefined)
    })
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
    // ranges, row count) before ever hitting the network.
    const validation = validateTaskTemplateItemRows(itemRows, t)
    setItemErrors(validation.errors)
    setItemsError(validation.formError)
    if (validation.formError !== null) {
      return
    }

    const errorFields: Path<TaskTemplateFormValues>[] = [...SERVER_ERROR_FIELDS]
    const orderedRowIds = itemRows.map((row) => row.id)

    try {
      if (mode.type === 'edit') {
        const saved = await updateTaskTemplate(
          mode.taskTemplate.id,
          buildUpdatePayload(values, itemRows, mode.taskTemplate),
        )
        queryClient.setQueryData(['task-templates', 'detail', mode.taskTemplate.id], saved)
        toast.success(t('taskTemplates.form.updated'))
        onSuccess(saved)
        return
      }

      // Step 2 (create): POST the template with every row in visual order.
      const created = await createTaskTemplate(buildCreatePayload(values, itemRows))

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
      const appliedField = applyServerValidationErrors(error, form.setError, errorFields)

      let appliedItems = false
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const serverItemErrors = extractItemServerErrors(error.response.data?.errors, orderedRowIds)
        if (Object.keys(serverItemErrors).length > 0) {
          setItemErrors((current) => ({ ...current, ...serverItemErrors }))
          setItemsError(t('taskTemplates.form.items.hasErrors'))
          appliedItems = true
        }
      }

      if (!appliedField && !appliedItems) {
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
    reorderItemRows,
    stagedFilesByRow,
    addStagedRowFiles,
    removeStagedRowFile,
    onSubmit,
  }
}
