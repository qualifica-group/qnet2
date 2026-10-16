import { useTranslation } from 'react-i18next'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'
import { rowLabel } from '@/features/management-control/row-label'
import type { Exposure, ExposureGroupBy } from '@/features/management-control/exposure-types'

const FIGURES = ['receivable_open', 'payable_open', 'committed', 'net'] as const

/** Net exposure (receivable - payable - committed) by company or site (spec 0269 D-9). */
export function ExposureByDimension({ data, groupBy }: { data: Exposure; groupBy: ExposureGroupBy }) {
  const { t } = useTranslation()
  const { exposure } = data
  const dimension = t(`managementControl.exposure.filters.groupBys.${groupBy}`)
  const totals = {
    receivable_open: exposure.receivable_open,
    payable_open: exposure.payable_open,
    committed: exposure.committed_total,
    net: exposure.net,
  }

  return (
    <ExposureSection title={t('managementControl.exposure.dimension.title', { dimension })} hint={t('managementControl.exposure.dimension.hint')}>
      <ExposureTable caption={t('managementControl.exposure.dimension.caption', { dimension })}>
        <thead className="bg-muted/40">
          <tr>
            <th scope="col" className={HEAD_CELL}>{dimension}</th>
            {FIGURES.map((figure) => (
              <th key={figure} scope="col" className={NUMERIC_HEAD_CELL}>
                {t(`managementControl.exposure.dimension.${figure}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {exposure.by_dimension.map((row) => (
            <tr key={row.key} className="border-t hover:bg-muted/40">
              <th scope="row" className={`${CELL} text-left font-medium`}>{rowLabel(t, row)}</th>
              {FIGURES.map((figure) => (
                <td key={figure} className={NUMERIC_CELL}><MoneyValue value={row[figure]} /></td>
              ))}
            </tr>
          ))}
        </tbody>
        <tfoot className="border-t-2 font-semibold">
          <tr>
            <th scope="row" className={`${CELL} text-left`}>{t('managementControl.exposure.dimension.total')}</th>
            {FIGURES.map((figure) => (
              <td key={figure} className={NUMERIC_CELL}><MoneyValue value={totals[figure]} /></td>
            ))}
          </tr>
        </tfoot>
      </ExposureTable>
    </ExposureSection>
  )
}
