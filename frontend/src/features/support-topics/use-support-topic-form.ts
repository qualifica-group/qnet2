import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createSupportTopic, supportTopicDetailQueryKey, updateSupportTopic } from '@/features/support-topics/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
  valuesOfDetail,
} from '@/features/support-topics/support-topic-form-payload'
import { buildSupportTopicSchema, type SupportTopicFormValues } from '@/features/support-topics/support-topic-schema'
import type { SupportTopicDetail, SupportTopicFormMode } from '@/features/support-topics/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS: Path<SupportTopicFormValues>[] = [
  'name',
  'description',
  'icon',
  'sort_order',
  'is_active',
  'task_type_id',
  'task_category_id',
  'task_priority_id',
  'urgent_task_priority_id',
  'task_importance_id',
  'due_days',
  'urgent_due_days',
  'is_private',
  'requires_closure_feedback',
  'assignee_ids',
  'watcher_ids',
]

/** Defaults of a brand-new topic: `due_days` 2 mirrors the server default (spec 0334 D-8). */
const CREATE_DEFAULTS: SupportTopicFormValues = {
  name: '',
  description: null,
  icon: '',
  sort_order: 0,
  is_active: true,
  task_type_id: null,
  task_category_id: null,
  task_priority_id: null,
  urgent_task_priority_id: null,
  task_importance_id: null,
  due_days: 2,
  urgent_due_days: null,
  is_private: false,
  requires_closure_feedback: false,
  assignee_ids: [],
  watcher_ids: [],
}

interface UseSupportTopicFormArgs {
  mode: SupportTopicFormMode
  onSuccess: (supportTopic: SupportTopicDetail) => void
}

/** Owns RHF/Zod wiring, defaults, 422 mapping and the create/update submit of the support topic form. */
export function useSupportTopicForm({ mode, onSuccess }: UseSupportTopicFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildSupportTopicSchema(t), [t])
  const defaultValues = useMemo<SupportTopicFormValues>(
    () => (mode.type === 'edit' ? valuesOfDetail(mode.supportTopic) : CREATE_DEFAULTS),
    [mode],
  )

  const form = useForm<SupportTopicFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: SupportTopicFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateSupportTopic(mode.supportTopic.id, buildUpdatePayload(values, mode.supportTopic))
        queryClient.setQueryData(supportTopicDetailQueryKey(mode.supportTopic.id), saved)
        toast.success(t('supportTopics.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createSupportTopic(buildCreatePayload(values))
      toast.success(t('supportTopics.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('supportTopics.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
