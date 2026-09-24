/**
 * Owns the tasks list's quick-create row (spec 0156 D-7): RHF/Zod wiring,
 * defaults (0154 D-8's predefined rows for type/priority/importance, today
 * for the due date, the connected actor as requester+assignee) and the
 * create submit — the row component stays UI-only, mirroring `useTaskForm`'s
 * own split.
 */
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import { useAuth } from '@/features/auth/use-auth'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTask } from '@/features/tasks/api'
import { useTaskLookupDefaults } from '@/features/tasks/use-task-lookup-defaults'
import type { CreateTaskPayload } from '@/features/tasks/types'

function buildSchema(t: TFunction) {
  return z
    .object({
      title: z.string().trim().min(1, t('tasks.quickCreate.titleRequired')),
      task_type_id: z.number().nullable(),
      task_priority_id: z.number().nullable(),
      task_importance_id: z.number().nullable(),
      task_status_id: z.number().nullable(),
      end_date: z.string().min(1, t('tasks.quickCreate.endDateRequired')),
      requester_id: z.number().nullable(),
      assignee_ids: z.array(z.number()),
      watcher_ids: z.array(z.number()),
    })
    .superRefine((values, ctx) => {
      if (values.requester_id === null) {
        ctx.addIssue({ code: 'custom', path: ['requester_id'], message: t('tasks.quickCreate.requesterRequired') })
      }
      if (values.assignee_ids.length === 0) {
        ctx.addIssue({ code: 'custom', path: ['assignee_ids'], message: t('tasks.quickCreate.assigneesRequired') })
      }
    })
}

export type TaskQuickCreateFormValues = z.infer<ReturnType<typeof buildSchema>>

/** Today as `YYYY-MM-DD`, the row's own default due date (D-7). */
function todayIsoDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

function quickCreateDefaults(requesterId: number | null): TaskQuickCreateFormValues {
  return {
    title: '',
    task_type_id: null,
    task_priority_id: null,
    task_importance_id: null,
    task_status_id: null,
    end_date: todayIsoDate(),
    requester_id: requesterId,
    assignee_ids: requesterId !== null ? [requesterId] : [],
    watcher_ids: [],
  }
}

/** Exported for `use-task-quick-create-row.test.ts` — the form/payload shapes diverge just enough (`task_status_id` optional) to be worth a direct unit test. */
export function buildQuickCreatePayload(values: TaskQuickCreateFormValues): CreateTaskPayload {
  return {
    title: values.title.trim(),
    requester_id: values.requester_id as number,
    end_date: values.end_date,
    assignee_ids: values.assignee_ids,
    watcher_ids: values.watcher_ids,
    task_type_id: values.task_type_id,
    task_priority_id: values.task_priority_id,
    task_importance_id: values.task_importance_id,
    ...(values.task_status_id !== null ? { task_status_id: values.task_status_id } : {}),
  }
}

export interface UseTaskQuickCreateRowArgs {
  /** Called after a successful create, so the caller refreshes the grid. */
  onCreated: () => void
}

export function useTaskQuickCreateRow({ onCreated }: UseTaskQuickCreateRowArgs) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const requesterId = user?.id ?? null

  const form = useForm<TaskQuickCreateFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: quickCreateDefaults(requesterId),
  })

  // Spec 0154 D-8's predefined rows, the same precompile the full create form
  // applies (`useTaskForm`): fill only while the field is still untouched, so
  // a fetch that resolves after the operator already picked something never
  // overwrites the choice.
  const lookupDefaults = useTaskLookupDefaults(true)
  useEffect(() => {
    if (lookupDefaults.taskTypeId !== null && form.getValues('task_type_id') === null) {
      form.setValue('task_type_id', lookupDefaults.taskTypeId)
    }
    if (lookupDefaults.taskPriorityId !== null && form.getValues('task_priority_id') === null) {
      form.setValue('task_priority_id', lookupDefaults.taskPriorityId)
    }
    if (lookupDefaults.taskImportanceId !== null && form.getValues('task_importance_id') === null) {
      form.setValue('task_importance_id', lookupDefaults.taskImportanceId)
    }
  }, [lookupDefaults, form])

  const onSubmit = async (values: TaskQuickCreateFormValues) => {
    try {
      await createTask(buildQuickCreatePayload(values))
      toast.success(t('tasks.form.created'))
      form.reset(quickCreateDefaults(requesterId))
      onCreated()
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, [
        'title',
        'task_type_id',
        'task_priority_id',
        'task_importance_id',
        'task_status_id',
        'end_date',
        'requester_id',
        'assignee_ids',
        'watcher_ids',
      ])
      if (!handled) {
        toast.error(t('tasks.form.genericError'))
      }
    }
  }

  return { form, onSubmit }
}
