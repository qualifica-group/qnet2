import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import type { UseQueryResult } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { formatQuantityWithUnit } from '@/features/stock/quantity'
import type { StockBalance } from '@/features/stock/types'
import { formatDate } from '@/lib/formatting/date-display'

/** Which side the list is read from: a product lists where it sits and who holds it; a person lists what they hold. */
export type StockBalanceListMode = 'product' | 'user'

function BalanceRow({ balance, mode, action }: { balance: StockBalance; mode: StockBalanceListMode; action?: ReactNode }) {
  const { t } = useTranslation()
  const title =
    mode === 'product'
      ? (balance.user?.name ?? t('stock.holder.inSite'))
      : `${balance.product.name} (${balance.product.code})`

  return (
    <li className="flex flex-wrap items-center gap-2 rounded-lg border bg-card p-3">
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="truncate text-sm font-medium">{title}</span>
        <span className="truncate text-xs text-muted-foreground">
          {mode === 'product'
            ? balance.operational_site.label
            : `${balance.operational_site.label} · ${t('stock.list.lastMoved', { date: formatDate(balance.last_moved_on) })}`}
        </span>
      </div>
      <span className="text-sm font-semibold tabular-nums">{formatQuantityWithUnit(balance)}</span>
      {action}
    </li>
  )
}

interface StockBalanceListProps {
  query: UseQueryResult<StockBalance[]>
  mode: StockBalanceListMode
  /** Per-row action (e.g. Restituisci), rendered only by the caller that is allowed to. */
  renderAction?: (balance: StockBalance) => ReactNode
}

/** Loading / error / empty / populated body shared by the product and the user sections (spec 0307). */
export function StockBalanceList({ query, mode, renderAction }: StockBalanceListProps) {
  const { t, i18n } = useTranslation()

  if (query.isLoading) {
    return <Skeleton className="h-16 w-full" />
  }
  if (query.isError) {
    return (
      <div className="flex items-center gap-2 text-sm text-destructive" role="alert">
        {apiErrorMessage(query.error, t('stock.list.loadError'), i18n)}
        <Button type="button" variant="outline" size="sm" onClick={() => void query.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  const balances = query.data ?? []
  if (balances.length === 0) {
    return <p className="text-sm text-muted-foreground">{t(mode === 'product' ? 'stock.list.emptyProduct' : 'stock.list.emptyUser')}</p>
  }

  return (
    <ul className="flex flex-col gap-2">
      {balances.map((balance) => (
        <BalanceRow key={balance.id} balance={balance} mode={mode} action={renderAction?.(balance)} />
      ))}
    </ul>
  )
}
