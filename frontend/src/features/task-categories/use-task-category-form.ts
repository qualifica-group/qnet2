import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTaskCategory, updateTaskCategory } from '@/features/task-categories/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-categories/task-category-form-payload'
import {
  buildCreateTaskCategorySchema,
  buildUpdateTaskCategorySchema,
  type CreateTaskCategoryFormValues,
  type UpdateTaskCategoryFormValues,
} from '@/features/task-categories/task-category-schema'
import type { TaskCategoryDetail, TaskCategoryFormMode } from '@/features/task-categories/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'icon',
  'is_active',
] as const

export type TaskCategoryFormValues = CreateTaskCategoryFormValues & UpdateTaskCategoryFormValues

interface UseTaskCategoryFormArgs {
  mode: TaskCategoryFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (taskCategory: TaskCategoryDetail) => void
}

/**
 * Owns every non-render concern of `TaskCategoryForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useTaskCategoryForm({ mode, onSuccess }: UseTaskCategoryFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateTaskCategorySchema(t) : buildCreateTaskCategorySchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<TaskCategoryFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.taskCategory.name,
        description: mode.taskCategory.description,
        color: mode.taskCategory.color,
        icon: mode.taskCategory.icon ?? '',
        is_active: mode.taskCategory.is_active,
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

  const form = useForm<TaskCategoryFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: TaskCategoryFormValues) => {
    setServerError(null)
    const errorFields: Path<TaskCategoryFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateTaskCategory(
          mode.taskCategory.id,
          buildUpdatePayload(values, mode.taskCategory),
        )
        queryClient.setQueryData(['task-categories', 'detail', mode.taskCategory.id], saved)
        toast.success(t('taskCategories.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createTaskCategory(buildCreatePayload(values))
      toast.success(t('taskCategories.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('taskCategories.form.genericError'))
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
