import { useTranslation } from 'react-i18next'
import { formatHours } from '@/features/management-control/exposure-format'

interface HoursValueProps {
  laborMinutes: number
  unpricedMinutes: number
}

/** Timesheet hours as h:mm, with the part not valued (users with no hourly cost) noted underneath when there is one (spec 0272 D-2). */
export function HoursValue({ laborMinutes, unpricedMinutes }: HoursValueProps) {
  const { t } = useTranslation()

  return (
    <>
      <span className="tabular-nums">{formatHours(laborMinutes)}</span>
      {unpricedMinutes > 0 ? (
        <span className="block text-xs font-normal text-muted-foreground" title={t('managementControl.functions.unpricedHint')}>
          {t('managementControl.functions.unpriced', { hours: formatHours(unpricedMinutes) })}
        </span>
      ) : null}
    </>
  )
}
