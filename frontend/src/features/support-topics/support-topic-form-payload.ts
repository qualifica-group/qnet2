import type {
  CreateSupportTopicPayload,
  SupportTopicDetail,
  UpdateSupportTopicPayload,
} from '@/features/support-topics/types'
import type { SupportTopicFormValues } from '@/features/support-topics/support-topic-schema'

/** The form's `icon` (empty string = unset) onto the backend's nullable value. */
function iconValue(icon: string): string | null {
  return icon === '' ? null : icon
}

function sameIds(a: number[], b: number[]): boolean {
  return a.length === b.length && [...a].sort().join() === [...b].sort().join()
}

export function buildCreatePayload(values: SupportTopicFormValues): CreateSupportTopicPayload {
  return { ...values, icon: iconValue(values.icon) }
}

/** The form values describing a saved record (also the edit form's defaults). */
export function valuesOfDetail(topic: SupportTopicDetail): SupportTopicFormValues {
  return {
    name: topic.name,
    description: topic.description,
    icon: topic.icon ?? '',
    sort_order: topic.sort_order,
    is_active: topic.is_active,
    task_type_id: topic.task_type?.id ?? null,
    task_category_id: topic.task_category?.id ?? null,
    task_priority_id: topic.task_priority?.id ?? null,
    urgent_task_priority_id: topic.urgent_task_priority?.id ?? null,
    task_importance_id: topic.task_importance?.id ?? null,
    due_days: topic.due_days,
    urgent_due_days: topic.urgent_due_days,
    is_private: topic.is_private,
    requires_closure_feedback: topic.requires_closure_feedback,
    assignee_ids: topic.assignees.map((person) => person.id),
    watcher_ids: topic.watchers.map((person) => person.id),
  }
}

/** Partial PATCH payload: only the fields that changed from the saved record. */
export function buildUpdatePayload(
  values: SupportTopicFormValues,
  original: SupportTopicDetail,
): UpdateSupportTopicPayload {
  const before = valuesOfDetail(original)
  const payload: Record<string, unknown> = {}

  for (const key of Object.keys(values) as (keyof SupportTopicFormValues)[]) {
    const changed =
      key === 'assignee_ids' || key === 'watcher_ids'
        ? !sameIds(values[key], before[key])
        : values[key] !== before[key]
    if (changed) {
      payload[key] = key === 'icon' ? iconValue(values.icon) : values[key]
    }
  }

  return payload as UpdateSupportTopicPayload
}
