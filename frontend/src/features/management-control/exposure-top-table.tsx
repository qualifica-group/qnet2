import { useTranslation } from 'react-i18next'
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
import type { ExposureTopRow } from '@/features/management-control/exposure-types'

interface ExposureTopTableProps {
  side: 'receivable' | 'payable'
  rows: ExposureTopRow[]
}

/** Customers or suppliers with the highest overdue amount, linked to their ledger (spec 0269 D-7). */
export function ExposureTopTable({ side, rows }: ExposureTopTableProps) {
  const { t } = useTranslation()

  return (
    <ExposureSection title={t(`managementControl.exposure.top.${side}Title`)}>
      {rows.length === 0 ? (
        <p className="rounded-lg border bg-surface p-3 text-sm text-muted-foreground">{t('managementControl.exposure.top.empty')}</p>
      ) : (
        <ExposureTable caption={t(`managementControl.exposure.top.${side}Caption`)}>
          <thead className="bg-muted/40">
            <tr>
              <th scope="col" className={HEAD_CELL}>{t(`managementControl.exposure.top.${side}Registry`)}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.top.open')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.top.overdue')}</th>
              <th scope="col" className={HEAD_CELL}>{t('managementControl.exposure.top.worstBucket')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.registry.id} className="border-t hover:bg-muted/40">
                <th scope="row" className={`${CELL} text-left font-medium`}>
                  <RegistryLink registry={row.registry} />
                </th>
                <td className={NUMERIC_CELL}><MoneyValue value={row.open} /></td>
                <td className={NUMERIC_CELL}><MoneyValue value={row.overdue} /></td>
                <td className={CELL}>{t(`managementControl.exposure.aging.buckets.${row.worst_bucket}`)}</td>
              </tr>
            ))}
          </tbody>
        </ExposureTable>
      )}
    </ExposureSection>
  )
}
