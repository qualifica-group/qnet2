import { useTranslation } from 'react-i18next'
import type { Billing } from '@/features/management-control/billing-types'
import { CELL, ExposureTable, HEAD_CELL, NUMERIC_CELL, NUMERIC_HEAD_CELL } from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'

interface BillingProformaTablesProps {
  proforma: Billing['proforma']
  daysLabel: (days: number | null) => string
}

/** Open proforma by age in days, and the cycle by kind of proforma request (null kind = no request). */
export function BillingProformaTables({ proforma, daysLabel }: BillingProformaTablesProps) {
  const { t } = useTranslation()

  return (
    <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
      <div className="flex min-w-0 flex-col gap-2">
        <h4 className="text-sm font-medium">{t('managementControl.billing.proforma.age.title')}</h4>
        <ExposureTable caption={t('managementControl.billing.proforma.age.caption')}>
          <thead className="bg-muted/40">
            <tr>
              <th scope="col" className={HEAD_CELL}>{t('managementControl.billing.proforma.age.bucket')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.proforma.age.documents')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.proforma.age.amount')}</th>
            </tr>
          </thead>
          <tbody>
            {proforma.open_by_age.map((row) => (
              <tr key={row.bucket} className="border-t hover:bg-muted/40">
                <th scope="row" className={`${CELL} text-left font-medium`}>
                  {t(`managementControl.billing.proforma.age.buckets.${row.bucket}`)}
                </th>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{row.documents}</td>
                <td className={NUMERIC_CELL}><MoneyValue value={row.amount} /></td>
              </tr>
            ))}
          </tbody>
        </ExposureTable>
      </div>
      <div className="flex min-w-0 flex-col gap-2">
        <h4 className="text-sm font-medium">{t('managementControl.billing.proforma.kinds.title')}</h4>
        <ExposureTable caption={t('managementControl.billing.proforma.kinds.caption')}>
          <thead className="bg-muted/40">
            <tr>
              <th scope="col" className={HEAD_CELL}>{t('managementControl.billing.proforma.kinds.kind')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.proforma.issued')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.proforma.converted')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.proforma.open')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.billing.proforma.kinds.averageDays')}</th>
            </tr>
          </thead>
          <tbody>
            {proforma.by_kind.map((row) => (
              <tr key={row.kind ?? 'none'} className="border-t hover:bg-muted/40">
                <th scope="row" className={`${CELL} text-left font-medium`}>
                  {t(`managementControl.billing.proforma.kinds.values.${row.kind ?? 'none'}`)}
                </th>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{row.issued}</td>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{row.converted}</td>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{row.open}</td>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{daysLabel(row.average_days_to_invoice)}</td>
              </tr>
            ))}
          </tbody>
        </ExposureTable>
      </div>
    </div>
  )
}
