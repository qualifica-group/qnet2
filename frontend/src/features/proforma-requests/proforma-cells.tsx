import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES, CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'

const STATUS_COLORS: Record<string, string> = {
  pending: BADGE_COLOR_CLASSES.blue,
  issued: BADGE_COLOR_CLASSES.yellow,
}

/** Status pill: the color pairs with the "€" cell of the work orders grid. */
export function ProformaStatusBadgeCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell />
  }
  return (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={cn(BADGE_BASE, STATUS_COLORS[value])}>
        {t(`proformaRequests.statuses.${value}`)}
      </Badge>
    </div>
  )
}

/** Request kind (consultancy | institution) as a translated plain label. */
export function ProformaKindCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell align="left" />
  }
  return <span className="truncate">{t(`proformaRequests.kinds.${value}`)}</span>
}
