import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTaskStatus, updateTaskStatus } from '@/features/task-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-statuses/task-status-form-payload'
import {
  buildCreateTaskStatusSchema,
  buildUpdateTaskStatusSchema,
  type CreateTaskStatusFormValues,
  type UpdateTaskStatusFormValues,
} from '@/features/task-statuses/task-status-schema'
import type { TaskStatusDetail, TaskStatusFormMode } from '@/features/task-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'icon',
  'group',
  'is_active',
  'completion_percentage',
] as const

/** Default percentage of a brand-new custom status: it starts at the "open" end. */
const DEFAULT_COMPLETION_PERCENTAGE = 0

/** Phase a brand-new custom status is born in, matching its default percentage. */
const DEFAULT_GROUP = 'open'

export type TaskStatusFormValues = CreateTaskStatusFormValues & UpdateTaskStatusFormValues

interface UseTaskStatusFormArgs {
  mode: TaskStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskStatus: TaskStatusDetail) => void
}

/**
 * Owns every non-render concern of `TaskStatusForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useTaskStatusForm({ mode, onSuccess }: UseTaskStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateTaskStatusSchema(t) : buildCreateTaskStatusSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<TaskStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.taskStatus.name,
        description: mode.taskStatus.description,
        color: mode.taskStatus.color,
        icon: mode.taskStatus.icon ?? '',
        group: mode.taskStatus.group,
        is_active: mode.taskStatus.is_active,
        completion_percentage: mode.taskStatus.completion_percentage,
      }
    }
    return {
      name: '',
      description: null,
      color: '',
      icon: '',
      group: DEFAULT_GROUP,
      is_active: true,
      completion_percentage: DEFAULT_COMPLETION_PERCENTAGE,
    }
  }, [mode])

  const form = useForm<TaskStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: TaskStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<TaskStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateTaskStatus(
          mode.taskStatus.id,
          buildUpdatePayload(values, mode.taskStatus),
        )
        queryClient.setQueryData(['task-statuses', 'detail', mode.taskStatus.id], saved)
        toast.success(t('taskStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createTaskStatus(buildCreatePayload(values))
      toast.success(t('taskStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('taskStatuses.form.genericError'))
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
