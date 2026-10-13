import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { isPayloadTooLargeError } from '@/components/rich-text/rich-text-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTaskTemplate } from '@/features/task-templates/api'
import { buildCreatePayload } from '@/features/task-templates/task-template-form-payload'
import {
  buildCreateTaskTemplateSchema,
  type CreateTaskTemplateFormValues,
} from '@/features/task-templates/task-template-schema'
import { useTaskTemplateBoard } from '@/features/task-templates/use-task-template-board'
import type { TaskTemplateDetail } from '@/features/task-templates/types'

/** Server-side field names mapped onto the form for 422 handling. `items`/`stages` are never an RHF path: the board maps them. */
export const TASK_TEMPLATE_SERVER_ERROR_FIELDS: Path<CreateTaskTemplateFormValues>[] = ['name', 'description']

export type TaskTemplateFormValues = CreateTaskTemplateFormValues

interface UseTaskTemplateFormArgs {
  /** Called after a successful create so the caller can close + refresh. */
  onSuccess: (taskTemplate: TaskTemplateDetail) => void
}

/**
 * Owns every non-render concern of the task template CREATE form (spec 0227:
 * there is no edit form, the detail edits in place): RHF/Zod wiring for the
 * header fields, the "Fasi e attivita'" board (`useTaskTemplateBoard`, spread
 * so the form exposes its rows and handlers directly) and the create submit,
 * followed by the positional upload of the files staged on each row (D-9).
 */
export function useTaskTemplateForm({ onSuccess }: UseTaskTemplateFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const schema = useMemo(() => buildCreateTaskTemplateSchema(t), [t])
  const form = useForm<TaskTemplateFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', description: null, is_active: true },
  })
  const board = useTaskTemplateBoard(null)

  const onSubmit = async (values: TaskTemplateFormValues) => {
    setServerError(null)

    // Step 1: validate the board client-side before ever hitting the network.
    if (!board.validate()) {
      return
    }

    const sentRows = board.itemRows
    try {
      // Step 2: POST the template with every row and every stage in visual order.
      const created = await createTaskTemplate(buildCreatePayload(values, sentRows, board.stageRows))
      // Step 3: upload each row's staged files against the id the server assigned it.
      await board.uploadStagedFiles(sentRows, created.items)
      toast.success(t('taskTemplates.form.created'))
      onSuccess(created)
    } catch (error) {
      // Spec 0128: the header AND every row description can carry inline
      // `data:` images, so a 413 cannot be attributed to a single field.
      if (isPayloadTooLargeError(error)) {
        setServerError(t('richText.errors.payloadTooLarge'))
        return
      }
      const appliedField = applyServerValidationErrors(error, form.setError, TASK_TEMPLATE_SERVER_ERROR_FIELDS)
      const appliedRows = board.applyServerErrors(
        error,
        sentRows.map((row) => row.id),
        board.stageRows.map((row) => row.id),
      )
      if (!appliedField && !appliedRows) {
        setServerError(t('taskTemplates.form.genericError'))
      }
    }
  }

  return { ...board, form, serverError, onSubmit }
}
