import { useTranslation } from 'react-i18next'
import { OverviewCard } from '@/features/treasury-overview/overview-card'
import { OverviewFigure } from '@/features/treasury-overview/overview-figure'
import { OVERVIEW_LINKS, accountStatementLink } from '@/features/treasury-overview/overview-links'
import type { TreasuryOverview } from '@/features/treasury-overview/types'

/** Liquidity: total and every account with its balance (link to its statement). */
export function LiquidityCard({ liquidity }: { liquidity: TreasuryOverview['liquidity'] }) {
  const { t } = useTranslation()

  return (
    <OverviewCard title={t('treasuryOverview.liquidity.title')}>
      <OverviewFigure
        label={t('treasuryOverview.liquidity.total')}
        amount={liquidity.total}
        to={OVERVIEW_LINKS.statements}
        strong
      />
      {liquidity.accounts.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('treasuryOverview.liquidity.noAccounts')}</p>
      ) : (
        <ul className="flex flex-col gap-1 border-t pt-2" aria-label={t('treasuryOverview.liquidity.accounts')}>
          {liquidity.accounts.map((account) => (
            <li key={account.id}>
              <OverviewFigure
                label={`${account.name} · ${t(`treasuryOverview.accountTypes.${account.type}`)}`}
                amount={account.balance}
                to={accountStatementLink(account.id)}
              />
            </li>
          ))}
        </ul>
      )}
    </OverviewCard>
  )
}
