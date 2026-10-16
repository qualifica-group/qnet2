import { useTranslation } from 'react-i18next'
import { formatShare } from '@/features/management-control/exposure-format'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
  RegistryLink,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'
import type { ExposureConcentration as Concentration } from '@/features/management-control/exposure-types'

/** Share of the invoiced amount and of the overdue amount held by the top customers (spec 0269 D-10). */
export function ExposureConcentration({ data }: { data: Concentration }) {
  const { t, i18n } = useTranslation()

  return (
    <ExposureSection title={t('managementControl.exposure.concentration.title')} hint={t('managementControl.exposure.concentration.hint')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
          <dt className="line-clamp-2 text-xs text-muted-foreground">{t('managementControl.exposure.concentration.invoicedTotal')}</dt>
          <dd className="text-sm font-medium"><MoneyValue value={data.invoiced_total} /></dd>
        </div>
        <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
          <dt className="line-clamp-2 text-xs text-muted-foreground">{t('managementControl.exposure.concentration.overdueTotal')}</dt>
          <dd className="text-sm font-medium"><MoneyValue value={data.overdue_total} /></dd>
        </div>
        <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
          <dt className="line-clamp-2 text-xs text-muted-foreground">{t('managementControl.exposure.concentration.topInvoicedShare')}</dt>
          <dd className="text-sm font-medium tabular-nums">{formatShare(data.top_invoiced_share, i18n.language)}</dd>
        </div>
        <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
          <dt className="line-clamp-2 text-xs text-muted-foreground">{t('managementControl.exposure.concentration.topOverdueShare')}</dt>
          <dd className="text-sm font-medium tabular-nums">{formatShare(data.top_overdue_share, i18n.language)}</dd>
        </div>
      </dl>
      {data.rows.length === 0 ? (
        <p className="rounded-lg border bg-surface p-3 text-sm text-muted-foreground">{t('managementControl.exposure.concentration.empty')}</p>
      ) : (
        <ExposureTable caption={t('managementControl.exposure.concentration.caption')}>
          <thead className="bg-muted/40">
            <tr>
              <th scope="col" className={HEAD_CELL}>{t('managementControl.exposure.concentration.customer')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.concentration.invoiced')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.concentration.invoicedShare')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.concentration.overdue')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.concentration.overdueShare')}</th>
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row) => (
              <tr key={row.registry.id} className="border-t hover:bg-muted/40">
                <th scope="row" className={`${CELL} text-left font-medium`}><RegistryLink registry={row.registry} /></th>
                <td className={NUMERIC_CELL}><MoneyValue value={row.invoiced} /></td>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{formatShare(row.invoiced_share, i18n.language)}</td>
                <td className={NUMERIC_CELL}><MoneyValue value={row.overdue} /></td>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{formatShare(row.overdue_share, i18n.language)}</td>
              </tr>
            ))}
          </tbody>
        </ExposureTable>
      )}
    </ExposureSection>
  )
}
