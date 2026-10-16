import { useTranslation } from 'react-i18next'
import { ExposureCostTypeTable } from '@/features/management-control/exposure-cost-type-table'
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
import type { Exposure } from '@/features/management-control/exposure-types'

const TOTALS = ['total', 'with_work_order', 'without_work_order'] as const

/** Open supplier orders not yet invoiced (committed) and the payable by cost type (spec 0269 D-8). */
export function ExposureCommitted({ data }: { data: Exposure }) {
  const { t } = useTranslation()
  const { committed, payable } = data

  return (
    <ExposureSection title={t('managementControl.exposure.committed.title')} hint={t('managementControl.exposure.committed.hint')}>
      <dl className="grid grid-cols-1 gap-2 sm:grid-cols-3">
        {TOTALS.map((field) => (
          <div key={field} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
            <dt className="truncate text-xs text-muted-foreground">{t(`managementControl.exposure.committed.${field}`)}</dt>
            <dd className="text-sm font-medium"><MoneyValue value={committed[field]} /></dd>
          </div>
        ))}
      </dl>
      <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
        <ExposureTable caption={t('managementControl.exposure.committed.bySupplier')}>
          <thead className="bg-muted/40">
            <tr>
              <th scope="col" className={HEAD_CELL}>{t('managementControl.exposure.committed.supplier')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.committed.orders')}</th>
              <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.committed.committed')}</th>
            </tr>
          </thead>
          <tbody>
            {committed.by_supplier.map((row) => (
              <tr key={row.registry.id} className="border-t hover:bg-muted/40">
                <th scope="row" className={`${CELL} text-left font-medium`}><RegistryLink registry={row.registry} /></th>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{row.orders}</td>
                <td className={NUMERIC_CELL}><MoneyValue value={row.committed} /></td>
              </tr>
            ))}
          </tbody>
        </ExposureTable>
        <ExposureCostTypeTable
          caption={t('managementControl.exposure.committed.byCostType')}
          amountLabel={t('managementControl.exposure.committed.committed')}
          rows={committed.by_cost_type}
          showOverdue={false}
        />
      </div>
      <ExposureCostTypeTable
        caption={t('managementControl.exposure.committed.payableByCostType')}
        amountLabel={t('managementControl.exposure.top.open')}
        rows={payable.by_cost_type}
        showOverdue
      />
    </ExposureSection>
  )
}
