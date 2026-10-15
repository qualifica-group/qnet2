import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import type { LedgerStatus } from '@/features/ledger/types'

const STATUS_COLORS: Record<LedgerStatus, string> = {
  paid: BADGE_COLOR_CLASSES.green,
  not_due: BADGE_COLOR_CLASSES.blue,
  overdue: BADGE_COLOR_CLASSES.amber,
  seriously_overdue: BADGE_COLOR_CLASSES.red,
}

/** Document status: colour plus text, never colour alone. */
export function LedgerStatusBadge({ status }: { status: LedgerStatus }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, STATUS_COLORS[status])}>
      {t(`ledger.status.${status}`)}
    </Badge>
  )
}
