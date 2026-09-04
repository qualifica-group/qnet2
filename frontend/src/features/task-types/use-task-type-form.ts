import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTaskType, updateTaskType } from '@/features/task-types/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-types/task-type-form-payload'
import {
  buildCreateTaskTypeSchema,
  buildUpdateTaskTypeSchema,
  type CreateTaskTypeFormValues,
  type UpdateTaskTypeFormValues,
} from '@/features/task-types/task-type-schema'
import type { TaskTypeDetail, TaskTypeFormMode } from '@/features/task-types/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'icon',
  'is_active',
] as const

export type TaskTypeFormValues = CreateTaskTypeFormValues & UpdateTaskTypeFormValues

interface UseTaskTypeFormArgs {
  mode: TaskTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskType: TaskTypeDetail) => void
}

/**
 * Owns every non-render concern of `TaskTypeForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useTaskTypeForm({ mode, onSuccess }: UseTaskTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateTaskTypeSchema(t) : buildCreateTaskTypeSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<TaskTypeFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.taskType.name,
        description: mode.taskType.description,
        color: mode.taskType.color,
        icon: mode.taskType.icon ?? '',
        is_active: mode.taskType.is_active,
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

  const form = useForm<TaskTypeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: TaskTypeFormValues) => {
    setServerError(null)
    const errorFields: Path<TaskTypeFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateTaskType(
          mode.taskType.id,
          buildUpdatePayload(values, mode.taskType),
        )
        queryClient.setQueryData(['task-types', 'detail', mode.taskType.id], saved)
        toast.success(t('taskTypes.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createTaskType(buildCreatePayload(values))
      toast.success(t('taskTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('taskTypes.form.genericError'))
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
