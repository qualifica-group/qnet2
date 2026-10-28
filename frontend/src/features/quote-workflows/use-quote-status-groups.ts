import { useTranslation } from 'react-i18next'
import { BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { WORKFLOW_STATUS_GROUPS, type WorkflowStatusGroupValue } from '@/features/quote-workflows/types'
import type { WorkflowStatusGroupOption } from '@/features/workflow-statuses/types'

/** Soft-badge color per group: open=green (active), pending=orange (waiting), validated=violet (accertato, non chiuso), closed_won=emerald (positive outcome), closed_lost=red (negative outcome). */
const GROUP_BADGE_CLASSES: Record<WorkflowStatusGroupValue, string> = {
  open: BADGE_COLOR_CLASSES.green,
  pending: BADGE_COLOR_CLASSES.orange,
  validated: BADGE_COLOR_CLASSES.violet,
  closed_won: BADGE_COLOR_CLASSES.emerald,
  closed_lost: BADGE_COLOR_CLASSES.red,
}

/** The quote vocabulary of the shared statuses editor's group select. */
export function useQuoteStatusGroups(): WorkflowStatusGroupOption[] {
  const { t } = useTranslation()

  return WORKFLOW_STATUS_GROUPS.map((group) => ({
    value: group,
    label: t(`quoteWorkflows.form.statuses.group.${group}`),
    badgeClassName: GROUP_BADGE_CLASSES[group],
  }))
}
