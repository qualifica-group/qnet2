import { useTranslation } from 'react-i18next'
import type { AccountFlows } from '@/features/management-control/accounts-types'
import {
  CELL,
  ExposureSection,
  ExposureTable,
  HEAD_CELL,
  NUMERIC_CELL,
  NUMERIC_HEAD_CELL,
} from '@/features/management-control/exposure-table'
import { MoneyValue } from '@/features/management-control/money-value'

const STICKY = 'sticky left-0 z-10 bg-card group-hover:bg-[color-mix(in_oklab,var(--card),var(--muted)_40%)]'

interface AccountsTableProps {
  side: 'collections' | 'payments'
  data: AccountFlows
}

/** Receipts or payments of the period by financial account; a document with no account is "unattributed" (spec 0271 D-6). */
export function AccountsTable({ side, data }: AccountsTableProps) {
  const { t } = useTranslation()
  const { total, installments, by_account: rows } = data[side]

  return (
    <ExposureSection title={t(`managementControl.accounts.${side}.title`)} hint={t(`managementControl.accounts.${side}.hint`)}>
      <ExposureTable caption={t(`managementControl.accounts.${side}.caption`)}>
        <thead>
          <tr>
            <th scope="col" className={`${HEAD_CELL} ${STICKY}`}>{t('managementControl.accounts.table.account')}</th>
            <th scope="col" className={HEAD_CELL}>{t('managementControl.accounts.table.type')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.accounts.table.amount')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('managementControl.accounts.table.installments')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => {
            const name = row.account?.name ?? t('managementControl.unattributed')
            return (
              <tr key={row.account?.id ?? 'unattributed'} className="group border-t hover:bg-muted/40">
                <th scope="row" title={name} className={`${CELL} ${STICKY} max-w-48 truncate text-left font-medium`}>{name}</th>
                <td className={CELL}>{row.account ? t(`managementControl.accounts.types.${row.account.type}`) : t('managementControl.accounts.table.noType')}</td>
                <td className={NUMERIC_CELL}><MoneyValue value={row.amount} /></td>
                <td className={`${NUMERIC_CELL} tabular-nums`}>{row.installments}</td>
              </tr>
            )
          })}
        </tbody>
        <tfoot className="border-t-2 font-semibold">
          <tr>
            <th scope="row" className={`${CELL} ${STICKY} text-left`}>{t('managementControl.accounts.table.total')}</th>
            <td className={CELL} />
            <td className={NUMERIC_CELL}><MoneyValue value={total} /></td>
            <td className={`${NUMERIC_CELL} tabular-nums`}>{installments}</td>
          </tr>
        </tfoot>
      </ExposureTable>
    </ExposureSection>
  )
}
