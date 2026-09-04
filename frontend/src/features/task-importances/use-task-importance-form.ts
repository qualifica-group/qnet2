import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTaskImportance, updateTaskImportance } from '@/features/task-importances/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-importances/task-importance-form-payload'
import {
  buildCreateTaskImportanceSchema,
  buildUpdateTaskImportanceSchema,
  type CreateTaskImportanceFormValues,
  type UpdateTaskImportanceFormValues,
} from '@/features/task-importances/task-importance-schema'
import type { TaskImportanceDetail, TaskImportanceFormMode } from '@/features/task-importances/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'icon',
  'is_active',
] as const

export type TaskImportanceFormValues = CreateTaskImportanceFormValues & UpdateTaskImportanceFormValues

interface UseTaskImportanceFormArgs {
  mode: TaskImportanceFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskImportance: TaskImportanceDetail) => void
}

/**
 * Owns every non-render concern of `TaskImportanceForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useTaskImportanceForm({ mode, onSuccess }: UseTaskImportanceFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateTaskImportanceSchema(t) : buildCreateTaskImportanceSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<TaskImportanceFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.taskImportance.name,
        description: mode.taskImportance.description,
        color: mode.taskImportance.color,
        icon: mode.taskImportance.icon ?? '',
        is_active: mode.taskImportance.is_active,
      }
    }
    return {
      name: '',
      description: null,
      color: '',
      icon: '',
      is_active: true,
    }
  }, [mode])

  const form = useForm<TaskImportanceFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: TaskImportanceFormValues) => {
    setServerError(null)
    const errorFields: Path<TaskImportanceFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateTaskImportance(
          mode.taskImportance.id,
          buildUpdatePayload(values, mode.taskImportance),
        )
        queryClient.setQueryData(['task-importances', 'detail', mode.taskImportance.id], saved)
        toast.success(t('taskImportances.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createTaskImportance(buildCreatePayload(values))
      toast.success(t('taskImportances.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('taskImportances.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
