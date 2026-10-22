/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { formatAbsenceMinutes } from '@/features/absences/absence-duration-format'
import type { TableRendererMap } from '@/features/table/renderer-registry'

const EMPTY = <span className="text-muted-foreground">—</span>

/** Duration in days + hours of the person's working day (`daily_minutes` travels on the row). */
function MinutesCell({ value, data }: ICellRendererParams) {
  if (typeof value !== 'number') return EMPTY
  const dailyMinutes = typeof data?.daily_minutes === 'number' ? data.daily_minutes : 0
  return <span>{formatAbsenceMinutes(value, dailyMinutes, i18n.t)}</span>
}

function DayPartCell({ value }: ICellRendererParams) {
  return typeof value === 'string' && value !== '' ? (
    <span>{i18n.t(`absences.requests.form.dayParts.${value}`)}</span>
  ) : (
    EMPTY
  )
}

function ShortNoticeCell({ value }: ICellRendererParams) {
  return value === true ? <Badge variant="destructive">{i18n.t('absences.badges.shortNotice')}</Badge> : EMPTY
}

/** Custom cells of the three request grids, keyed by the backend column id. */
export const absenceColumnRenderers: TableRendererMap = {
  minutes: (params) => <MinutesCell {...params} />,
  day_part: (params) => <DayPartCell {...params} />,
  is_short_notice: (params) => <ShortNoticeCell {...params} />,
}
