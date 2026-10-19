import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { formatSignedMinutes } from '@/features/absences/balances/balance-format'
import { useAbsenceBalances } from '@/features/absences/balances/use-absence-balances'
import type { AbsenceRequestFormValues } from '@/features/absences/requests/absence-request-schema'
import type { AbsenceTypeOption } from '@/features/absences/types'
import { timeValueToMinutes } from '@/features/time-entries/time-entry-format'

const YEAR_PATTERN = /^(\d{4})-/

interface AbsenceBalanceHintProps {
  type: AbsenceTypeOption
}

/**
 * Current residual of the picked type and, only for an hourly request, the
 * residual after it. A full-day request has no preview: its duration is fixed
 * on submit from the working days (the contract has no preview endpoint).
 */
export function AbsenceBalanceHint({ type }: AbsenceBalanceHintProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AbsenceRequestFormValues>()
  const [startsOn, isFullDay, duration] = useWatch({
    control,
    name: ['starts_on', 'is_full_day', 'requested_minutes'],
  })
  const year = Number(YEAR_PATTERN.exec(startsOn)?.[1] ?? new Date().getFullYear())
  const { data, isPending, isError } = useAbsenceBalances({ year })

  if (!type.deducts_balance) {
    return <p className="text-xs text-muted-foreground">{t('absences.requests.form.balanceNoDeduct')}</p>
  }
  if (isPending) {
    return null
  }
  const row = data?.balances.find((balance) => balance.absence_type.id === type.id)
  if (isError || !row) {
    return <p className="text-xs text-muted-foreground">{t('absences.requests.form.balanceUnavailable')}</p>
  }

  const requested = isFullDay ? 0 : (timeValueToMinutes(duration) ?? 0)

  return (
    <div className="space-y-0.5 text-xs text-muted-foreground">
      <p>
        {t('absences.requests.form.balanceCurrent', {
          year,
          value: formatSignedMinutes(row.residual_minutes),
        })}
      </p>
      {isFullDay ? (
        <p>{t('absences.requests.form.fullDayDuration')}</p>
      ) : requested > 0 ? (
        <p>
          {t('absences.requests.form.balanceAfter', {
            value: formatSignedMinutes(row.residual_minutes - requested),
          })}
        </p>
      ) : null}
    </div>
  )
}
