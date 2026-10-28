import { useTranslation } from 'react-i18next'
import { BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { WORK_ORDER_STATUS_GROUPS, type WorkOrderStatusGroupValue } from '@/features/work-order-workflows/types'
import type { WorkflowStatusGroupOption } from '@/features/workflow-statuses/types'

/** Soft-badge color per group: open=green, in_progress=orange, completed=emerald, closed=red. */
const GROUP_BADGE_CLASSES: Record<WorkOrderStatusGroupValue, string> = {
  open: BADGE_COLOR_CLASSES.green,
  in_progress: BADGE_COLOR_CLASSES.orange,
  completed: BADGE_COLOR_CLASSES.emerald,
  closed: BADGE_COLOR_CLASSES.red,
}

/** The work order vocabulary of the shared statuses editor's group select. */
export function useWorkOrderStatusGroups(): WorkflowStatusGroupOption[] {
  const { t } = useTranslation()

  return WORK_ORDER_STATUS_GROUPS.map((group) => ({
    value: group,
    label: t(`workOrderWorkflows.form.statuses.group.${group}`),
    badgeClassName: GROUP_BADGE_CLASSES[group],
  }))
}
