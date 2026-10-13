import { useMemo, useState } from 'react'
import { useForm, type FieldErrors } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { isPayloadTooLargeError } from '@/components/rich-text/rich-text-errors'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { taskTemplateDetailQueryKey, updateTaskTemplate } from '@/features/task-templates/api'
import { buildBoardUpdatePayload } from '@/features/task-templates/task-template-form-payload'
import { buildUpdateTaskTemplateSchema } from '@/features/task-templates/task-template-schema'
import { useTaskTemplateBoard } from '@/features/task-templates/use-task-template-board'
import {
  TASK_TEMPLATE_SERVER_ERROR_FIELDS,
  type TaskTemplateFormValues,
} from '@/features/task-templates/use-task-template-form'
import type {
  TaskTemplateDetailWithPermissions,
  UpdateTaskTemplatePayload,
} from '@/features/task-templates/types'

/** The board row's key: `items` is also the field permission gating the whole board (spec 0124 D-1). */
export const TASK_TEMPLATE_BOARD_FIELD = 'items'

const HEADER_FIELDS = ['name', 'description', 'is_active'] as const

function headerValuesOf(taskTemplate: TaskTemplateDetailWithPermissions): TaskTemplateFormValues {
  return { name: taskTemplate.name, description: taskTemplate.description, is_active: taskTemplate.is_active }
}

/** Only the header fields that differ from the saved template: a row sends its own field alone. */
function changedHeaderFields(
  values: TaskTemplateFormValues,
  taskTemplate: TaskTemplateDetailWithPermissions,
): UpdateTaskTemplatePayload {
  const payload: UpdateTaskTemplatePayload = {}
  for (const field of HEADER_FIELDS) {
    if (values[field] !== taskTemplate[field]) {
      Object.assign(payload, { [field]: values[field] })
    }
  }
  return payload
}

/**
 * Drives the task template detail's in-place editing (spec 0227): ONE row
 * open at a time. A header row (Nome, Descrizione, Attivo) PATCHes its own
 * field; the "Fasi e attivita'" row PATCHes the board alone, a full sync of
 * items and stages (D-3), then uploads the files staged on its new rows. The
 * saved template (with permissions) replaces the cached detail.
 */
export function useTaskTemplateInlineEdit(taskTemplate: TaskTemplateDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const schema = useMemo(() => buildUpdateTaskTemplateSchema(t), [t])
  const values = useMemo(() => headerValuesOf(taskTemplate), [taskTemplate])
  // `values` re-syncs the rows on the saved template; `keepDirtyValues` spares an open editor.
  const form = useForm<TaskTemplateFormValues>({
    resolver: zodResolver(schema),
    values,
    resetOptions: { keepDirtyValues: true },
  })
  const board = useTaskTemplateBoard(taskTemplate)

  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    setServerError(null)
  }

  const start = (field: string) => {
    discard()
    // A board session always starts from the template as saved now.
    if (field === TASK_TEMPLATE_BOARD_FIELD) {
      board.reset(taskTemplate)
    }
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  const persist = async (payload: UpdateTaskTemplatePayload, afterSave?: (saved: TaskTemplateDetailWithPermissions) => Promise<void>) => {
    setIsSaving(true)
    setServerError(null)
    const sentRowIds = board.itemRows.map((row) => row.id)
    const sentStageIds = board.stageRows.map((row) => row.id)
    try {
      const saved = await updateTaskTemplate(taskTemplate.id, payload)
      await afterSave?.(saved)
      queryClient.setQueryData(taskTemplateDetailQueryKey(taskTemplate.id), saved)
      toast.success(t('taskTemplates.form.updated'))
      setEditingField(null)
      onChanged?.()
    } catch (error) {
      if (isPayloadTooLargeError(error)) {
        setServerError(t('richText.errors.payloadTooLarge'))
        return
      }
      const appliedField = applyServerValidationErrors(error, form.setError, TASK_TEMPLATE_SERVER_ERROR_FIELDS)
      const appliedRows = board.applyServerErrors(error, sentRowIds, sentStageIds)
      if (!appliedField && !appliedRows) {
        setServerError(t('taskTemplates.form.genericError'))
      }
    } finally {
      setIsSaving(false)
    }
  }

  const saveBoard = () => {
    if (!board.validate()) {
      return
    }
    const sentRows = board.itemRows
    void persist(buildBoardUpdatePayload(sentRows, board.stageRows), (saved) =>
      board.uploadStagedFiles(sentRows, saved.items),
    )
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<TaskTemplateFormValues>) => {
    if (editingField === null || !(editingField in errors)) {
      toast.error(firstErrorMessage(errors) ?? t('taskTemplates.form.genericError'))
    }
  }

  const saveHeaderField = () => {
    void form.handleSubmit((submitted) => persist(changedHeaderFields(submitted, taskTemplate)), handleInvalid)()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save: editingField === TASK_TEMPLATE_BOARD_FIELD ? saveBoard : saveHeaderField,
    dismiss: cancel,
    isSaving,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline, board }
}

export type TaskTemplateDetailEditor = ReturnType<typeof useTaskTemplateInlineEdit>
