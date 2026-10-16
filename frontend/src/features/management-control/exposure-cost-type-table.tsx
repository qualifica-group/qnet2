import { useTranslation } from 'react-i18next'
import { CostTypeLabel } from '@/features/management-control/cost-type-label'
import {
  CELL,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'
import type { ExposureCostTypeRow } from '@/features/management-control/exposure-types'

interface ExposureCostTypeTableProps {
  caption: string
  amountLabel: string
  rows: ExposureCostTypeRow[]
  /** The payable side also splits out the overdue part; for the committed one it is always zero. */
  showOverdue: boolean
}

/** Amounts by cost type with the nature and the colour dot (spec 0269 D-8). */
export function ExposureCostTypeTable({ caption, amountLabel, rows, showOverdue }: ExposureCostTypeTableProps) {
  const { t } = useTranslation()

  return (
    <ExposureTable caption={caption}>
      <thead className="bg-muted/40">
        <tr>
          <th scope="col" className={HEAD_CELL}>{t('managementControl.structure.costType')}</th>
          <th scope="col" className={HEAD_CELL}>{t('managementControl.structure.nature')}</th>
          <th scope="col" className={NUMERIC_HEAD_CELL}>{amountLabel}</th>
          {showOverdue ? (
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.exposure.top.overdue')}</th>
          ) : null}
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={row.cost_type?.id ?? 'none'} className="border-t hover:bg-muted/40">
            <th scope="row" className={`${CELL} text-left font-medium`}>
              <CostTypeLabel costType={row.cost_type} />
            </th>
            <td className={CELL}>
              {row.cost_type?.nature ? t(`managementControl.structure.natures.${row.cost_type.nature}`) : ''}
            </td>
            <td className={NUMERIC_CELL}><MoneyValue value={row.open} /></td>
            {showOverdue ? <td className={NUMERIC_CELL}><MoneyValue value={row.overdue} /></td> : null}
          </tr>
        ))}
      </tbody>
    </ExposureTable>
  )
}
