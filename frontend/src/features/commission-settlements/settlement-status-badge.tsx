import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import type { SettlementStatus } from '@/features/commission-settlements/types'

const STATUS_COLORS: Record<SettlementStatus, string> = {
  DRAFT: BADGE_COLOR_CLASSES.amber,
  ISSUED: BADGE_COLOR_CLASSES.green,
  CANCELLED: BADGE_COLOR_CLASSES.slate,
}

/** Status pill of a settlement (label from the `commission_settlement_status` enum). */
export function SettlementStatusBadge({ status }: { status: SettlementStatus }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, STATUS_COLORS[status])}>
      {t(`enums.commission_settlement_status.${status}`)}
    </Badge>
  )
}
