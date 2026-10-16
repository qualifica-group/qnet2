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
import { PAYMENT_STATUSES, type Exposure } from '@/features/management-control/exposure-types'

const SIDES = ['receivable', 'payable'] as const

/** Documents by payment status, with their open amount, for both sides (spec 0269 D-5). */
export function ExposureStatusTable({ data }: { data: Exposure }) {
  const { t } = useTranslation()

  return (
    <ExposureSection title={t('managementControl.exposure.status.title')} hint={t('managementControl.exposure.status.hint')}>
      <ExposureTable caption={t('managementControl.exposure.status.caption')}>
        <thead className="bg-muted/40">
          <tr>
            <th scope="col" rowSpan={2} className={HEAD_CELL}>{t('managementControl.exposure.status.status')}</th>
            {SIDES.map((side) => (
              <th key={side} scope="colgroup" colSpan={2} className="px-2 py-1.5 text-center text-xs font-medium text-muted-foreground">
                {t(`managementControl.exposure.status.${side}`)}
              </th>
            ))}
          </tr>
          <tr>
            {SIDES.flatMap((side) => [
              <th key={`${side}-documents`} scope="col" className={NUMERIC_HEAD_CELL}>
                {t('managementControl.exposure.status.documents')}
              </th>,
              <th key={`${side}-open`} scope="col" className={NUMERIC_HEAD_CELL}>
                {t('managementControl.exposure.status.open')}
              </th>,
            ])}
          </tr>
        </thead>
        <tbody>
          {PAYMENT_STATUSES.map((status) => (
            <tr key={status} className="border-t hover:bg-muted/40">
              <th scope="row" className={`${CELL} text-left font-medium`}>
                {t(`managementControl.exposure.status.statuses.${status}`)}
              </th>
              {SIDES.flatMap((side) => [
                <td key={`${side}-documents`} className={`${NUMERIC_CELL} tabular-nums`}>
                  {data[side].by_status[status].documents}
                </td>,
                <td key={`${side}-open`} className={NUMERIC_CELL}>
                  <MoneyValue value={data[side].by_status[status].open} />
                </td>,
              ])}
            </tr>
          ))}
        </tbody>
      </ExposureTable>
    </ExposureSection>
  )
}
