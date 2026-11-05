import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { ForecastTable } from '@/features/treasury-overview/forecast-table'
import { LiquidityCard } from '@/features/treasury-overview/liquidity-card'
import { OverviewCard } from '@/features/treasury-overview/overview-card'
import { OVERVIEW_LINKS } from '@/features/treasury-overview/overview-links'
import { ScheduleCard } from '@/features/treasury-overview/schedule-card'
import { ToVerifyCard } from '@/features/treasury-overview/to-verify-card'
import type { TreasuryOverview } from '@/features/treasury-overview/types'

/** The blocks of the overview (spec 0362), pure rendering of the payload. */
export function TreasuryOverviewPanel({ data }: { data: TreasuryOverview }) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <LiquidityCard liquidity={data.liquidity} />
        <ScheduleCard side="receivable" data={data.receivable} to={OVERVIEW_LINKS.receivable} />
        <ScheduleCard side="payable" data={data.payable} to={OVERVIEW_LINKS.payable} />
        <OverviewCard title={t('treasuryOverview.netPosition.title')}>
          <p className="text-xl font-semibold tabular-nums">{formatEuro(data.net_position)}</p>
          <p className="text-xs text-muted-foreground">{t('treasuryOverview.netPosition.hint')}</p>
        </OverviewCard>
      </div>
      <div className="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <ForecastTable rows={data.forecast} />
        <ToVerifyCard toVerify={data.to_verify} />
      </div>
    </div>
  )
}
