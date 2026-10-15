import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import type { SettlementStatus } from '@/features/commission-settlements/types'

const STATUS_COLORS: Record<SettlementStatus, string> = {
  DRAFT: BADGE_COLOR_CLASSES.amber,
  ISSUED: BADGE_COLOR_CLASSES.blue,
  DOCUMENT_RECEIVED: BADGE_COLOR_CLASSES.violet,
  APPROVED: BADGE_COLOR_CLASSES.green,
  CANCELLED: BADGE_COLOR_CLASSES.slate,
}

/** Status pill of a settlement (label from the `commissionSettlements.statuses` keys). */
export function SettlementStatusBadge({ status }: { status: SettlementStatus }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, STATUS_COLORS[status])}>
      {t(`commissionSettlements.statuses.${status}`)}
    </Badge>
  )
}
