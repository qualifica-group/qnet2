import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTaskPriority, updateTaskPriority } from '@/features/task-priorities/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-priorities/task-priority-form-payload'
import {
  buildCreateTaskPrioritySchema,
  buildUpdateTaskPrioritySchema,
  type CreateTaskPriorityFormValues,
  type UpdateTaskPriorityFormValues,
} from '@/features/task-priorities/task-priority-schema'
import type { TaskPriorityDetail, TaskPriorityFormMode } from '@/features/task-priorities/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'icon',
  'is_active',
] as const

export type TaskPriorityFormValues = CreateTaskPriorityFormValues & UpdateTaskPriorityFormValues

interface UseTaskPriorityFormArgs {
  mode: TaskPriorityFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskPriority: TaskPriorityDetail) => void
}

/**
 * Owns every non-render concern of `TaskPriorityForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useTaskPriorityForm({ mode, onSuccess }: UseTaskPriorityFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateTaskPrioritySchema(t) : buildCreateTaskPrioritySchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<TaskPriorityFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.taskPriority.name,
        description: mode.taskPriority.description,
        color: mode.taskPriority.color,
        icon: mode.taskPriority.icon ?? '',
        is_active: mode.taskPriority.is_active,
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

  const form = useForm<TaskPriorityFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: TaskPriorityFormValues) => {
    setServerError(null)
    const errorFields: Path<TaskPriorityFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateTaskPriority(
          mode.taskPriority.id,
          buildUpdatePayload(values, mode.taskPriority),
        )
        queryClient.setQueryData(['task-priorities', 'detail', mode.taskPriority.id], saved)
        toast.success(t('taskPriorities.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createTaskPriority(buildCreatePayload(values))
      toast.success(t('taskPriorities.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('taskPriorities.form.genericError'))
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
