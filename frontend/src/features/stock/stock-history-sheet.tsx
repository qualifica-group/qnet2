import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Badge } from '@/components/ui/badge'
import { formatQuantity } from '@/features/stock/quantity'
import { useStockHistory } from '@/features/stock/use-stock-history'
import type { StockHistoryFilters, StockMovement } from '@/features/stock/types'
import { formatDate } from '@/lib/formatting/date-display'

/** "Sede A / Anna Rossi" or "Sede A" for the free stock of a site. */
function placeOf(site: { label: string } | null, user: { name: string } | null): string | null {
  if (site === null) {
    return null
  }
  return user === null ? site.label : `${site.label} / ${user.name}`
}

function MovementRow({ movement }: { movement: StockMovement }) {
  const { t } = useTranslation()
  const from = placeOf(movement.from_operational_site, movement.from_user)
  const to = placeOf(movement.to_operational_site, movement.to_user)

  return (
    <li className="flex flex-col gap-1 rounded-lg border bg-card p-3">
      <div className="flex flex-wrap items-center gap-2">
        <Badge variant="secondary">{t(`enums.stock_movement_type.${movement.type}`)}</Badge>
        <span className="text-sm font-semibold tabular-nums">
          {formatQuantity(movement.quantity)} {movement.product.unit_of_measure.code}
        </span>
        <span className="ml-auto text-xs text-muted-foreground">{formatDate(movement.moved_on)}</span>
      </div>
      <span className="text-xs text-muted-foreground">
        {[from, to].filter((part): part is string => part !== null).join(' → ')}
      </span>
      {movement.note ? <span className="text-xs whitespace-pre-line">{movement.note}</span> : null}
      <span className="text-xs text-muted-foreground">{t('stock.history.by', { name: movement.created_by.name })}</span>
    </li>
  )
}

function HistoryBody({ target }: { target: StockHistoryFilters }) {
  const { t, i18n } = useTranslation()
  const history = useStockHistory(target)

  if (history.isLoading) {
    return <Skeleton className="h-24 w-full" />
  }
  if (history.isError) {
    return (
      <div className="flex items-center gap-2 text-sm text-destructive" role="alert">
        {apiErrorMessage(history.error, t('stock.history.loadError'), i18n)}
        <Button type="button" variant="outline" size="sm" onClick={() => void history.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  const movements = history.data ?? []
  if (movements.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('stock.history.empty')}</p>
  }

  return (
    <ul className="flex flex-col gap-2">
      {movements.map((movement) => (
        <MovementRow key={movement.id} movement={movement} />
      ))}
    </ul>
  )
}

interface StockHistorySheetProps {
  /** The row whose movements are shown; `null` = closed. */
  target: StockHistoryFilters | null
  onClose: () => void
}

/** "Storico" of a stock row: the movements where its (site, user) pair is source or destination, newest first (spec 0307). */
export function StockHistorySheet({ target, onClose }: StockHistorySheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={target !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:stock-history">
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader>
          <SheetTitle>{t('stock.history.title')}</SheetTitle>
          <SheetDescription>{t('stock.history.description')}</SheetDescription>
        </SheetHeader>
        <div className="flex-1 overflow-y-auto p-4">{target !== null ? <HistoryBody target={target} /> : null}</div>
      </SheetContent>
    </Sheet>
  )
}
