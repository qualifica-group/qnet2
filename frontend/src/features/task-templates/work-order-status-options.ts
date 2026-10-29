import type { TFunction } from 'i18next'
import type { SearchableSelectOption } from '@/components/ui/searchable-select'
import type { WorkOrderStatusForSelectFields } from '@/features/task-templates/work-order-status-for-select-api'

/** Group id of the default status set (`work_order_workflow_id` is `null` there). */
const DEFAULT_SET_GROUP_ID = 0

/** Group headers are non-pickable options: negative ids can never collide with a status id. */
function headerIdOf(groupId: number): number {
  return -(groupId + 1)
}

function optionLabel(status: WorkOrderStatusForSelectFields): string {
  return status.progress_percentage !== null ? `${status.name} (${status.progress_percentage}%)` : status.name
}

/**
 * Groups the statuses by workflow, keeping the server order (default set
 * first, then workflows by name): an `ids` hydrated row the server appends
 * after the page lands back in its group. Each group opens with a disabled
 * header option and its statuses nest one level below it.
 */
export function buildGroupedStatusOptions(
  statuses: WorkOrderStatusForSelectFields[],
  t: TFunction,
): SearchableSelectOption[] {
  const groups = new Map<number, { title: string; statuses: WorkOrderStatusForSelectFields[] }>()
  for (const status of statuses) {
    const groupId = status.work_order_workflow_id ?? DEFAULT_SET_GROUP_ID
    const group = groups.get(groupId) ?? {
      title: status.work_order_workflow_name ?? t('taskTemplates.form.items.workOrderStatusDefaultSet'),
      statuses: [],
    }
    group.statuses.push(status)
    groups.set(groupId, group)
  }

  return [...groups].flatMap(([groupId, group]) => [
    { id: headerIdOf(groupId), name: group.title, disabled: true },
    ...group.statuses.map((status) => ({ id: status.id, name: optionLabel(status), depth: 1 })),
  ])
}
