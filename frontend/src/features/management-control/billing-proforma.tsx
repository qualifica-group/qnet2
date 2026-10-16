import { useTranslation } from 'react-i18next'
import type { Billing } from '@/features/management-control/billing-types'
import { BillingProformaTables } from '@/features/management-control/billing-proforma-tables'
import { ExposureSection } from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'

const STATUSES = ['issued', 'converted', 'open'] as const

/** Days of an average time, "n/a" when the period has no document to measure. */
function useDaysLabel() {
  const { t } = useTranslation()
  return (days: number | null) =>
    days === null ? t('managementControl.billing.notAvailable') : t('managementControl.billing.days', { count: days })
}

/** Proforma cycle (spec 0271 D-4, D-5): issued, converted and open, the average times, then the open ones by age and the cycle by kind. */
export function BillingProforma({ proforma }: { proforma: Billing['proforma'] }) {
  const { t } = useTranslation()
  const daysLabel = useDaysLabel()
  const times = [
    { key: 'toInvoice', days: proforma.converted.average_days_to_invoice },
    { key: 'toCollection', days: proforma.average_days_invoice_to_collection },
  ] as const

  return (
    <ExposureSection title={t('managementControl.billing.proforma.title')} hint={t('managementControl.billing.proforma.hint')}>
      <section aria-label={t('managementControl.billing.proforma.totalsTitle')}>
        <dl className="grid grid-cols-1 gap-2 sm:grid-cols-3">
          {STATUSES.map((status) => (
            <div key={status} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
              <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.billing.proforma.${status}`)}</dt>
              <dd className="text-sm font-medium"><MoneyValue value={proforma[status].amount} /></dd>
              <dd className="text-xs text-muted-foreground">
                {t('managementControl.billing.proforma.documents', { count: proforma[status].documents })}
              </dd>
            </div>
          ))}
        </dl>
      </section>
      <section aria-label={t('managementControl.billing.proforma.timesTitle')}>
        <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          {times.map(({ key, days }) => (
            <div key={key} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
              <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.billing.proforma.times.${key}`)}</dt>
              <dd className="text-sm font-medium tabular-nums">{daysLabel(days)}</dd>
            </div>
          ))}
        </dl>
      </section>
      <BillingProformaTables proforma={proforma} daysLabel={daysLabel} />
    </ExposureSection>
  )
}
