import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formatEuro } from '@/features/invoices/invoice-format'
import { OverviewCard } from '@/features/treasury-overview/overview-card'
import { OVERVIEW_LINKS } from '@/features/treasury-overview/overview-links'
import type { TreasuryOverview } from '@/features/treasury-overview/types'

/** Collections/payments with no account and the balance adjustments of the month. */
export function ToVerifyCard({ toVerify }: { toVerify: TreasuryOverview['to_verify'] }) {
  const { t } = useTranslation()
  const { unassigned, adjustments } = toVerify

  return (
    <OverviewCard title={t('treasuryOverview.toVerify.title')}>
      <div className="flex flex-col gap-0.5 text-sm">
        <Link to={OVERVIEW_LINKS.statements} className="font-medium hover:underline">
          {t('treasuryOverview.toVerify.unassigned', { count: unassigned.count })}
        </Link>
        <span className="text-xs text-muted-foreground">
          {t('treasuryOverview.toVerify.unassignedAmounts', {
            collections: formatEuro(unassigned.collections),
            payments: formatEuro(unassigned.payments),
          })}
        </span>
      </div>
      <div className="flex flex-col gap-0.5 border-t pt-2 text-sm">
        <Link to={OVERVIEW_LINKS.movements} className="font-medium hover:underline">
          {t('treasuryOverview.toVerify.adjustments', { count: adjustments.count, month: adjustments.month })}
        </Link>
        <span className="text-xs text-muted-foreground">
          {t('treasuryOverview.toVerify.adjustmentAmounts', {
            inflow: formatEuro(adjustments.inflow),
            outflow: formatEuro(adjustments.outflow),
          })}
        </span>
      </div>
    </OverviewCard>
  )
}
