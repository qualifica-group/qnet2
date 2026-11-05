import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formatEuro } from '@/features/invoices/invoice-format'
import { OverviewCard } from '@/features/treasury-overview/overview-card'
import { OVERVIEW_LINKS } from '@/features/treasury-overview/overview-links'
import type { ForecastRow } from '@/features/treasury-overview/types'

const CELL = 'px-2 py-1.5 text-right tabular-nums'

/** Expected collections, payments and balance at 30/60/90 days from the real liquidity (D-2). */
export function ForecastTable({ rows }: { rows: ForecastRow[] }) {
  const { t } = useTranslation()

  return (
    <OverviewCard title={t('treasuryOverview.forecast.title')}>
      <p className="text-xs text-muted-foreground">{t('treasuryOverview.forecast.hint')}</p>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[28rem] text-sm">
          <thead className="text-xs text-muted-foreground">
            <tr className="border-b">
              <th scope="col" className="px-2 py-1.5 text-left font-medium">
                {t('treasuryOverview.forecast.window')}
              </th>
              <th scope="col" className={`${CELL} font-medium`}>{t('treasuryOverview.forecast.receivable')}</th>
              <th scope="col" className={`${CELL} font-medium`}>{t('treasuryOverview.forecast.payable')}</th>
              <th scope="col" className={`${CELL} font-medium`}>{t('treasuryOverview.forecast.expectedBalance')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.days} className="border-b last:border-0">
                <th scope="row" className="px-2 py-1.5 text-left font-medium">
                  {t('treasuryOverview.forecast.days', { days: row.days, until: row.until })}
                </th>
                <td className={CELL}>
                  <Link to={OVERVIEW_LINKS.receivable} className="hover:underline">
                    {formatEuro(row.receivable)}
                  </Link>
                </td>
                <td className={CELL}>
                  <Link to={OVERVIEW_LINKS.payable} className="hover:underline">
                    {formatEuro(row.payable)}
                  </Link>
                </td>
                <td className={`${CELL} font-semibold`}>{formatEuro(row.expected_balance)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </OverviewCard>
  )
}
