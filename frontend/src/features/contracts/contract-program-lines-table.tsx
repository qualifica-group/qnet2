import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Skeleton } from '@/components/ui/skeleton'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { ContractProgrammableLine } from '@/features/contracts/types'

/** Checkbox / product / category / quantity / UM / occupation (D-11: no other columns). */
const LINES_GRID_CLASS = 'grid grid-cols-[28px_minmax(160px,1.4fr)_minmax(100px,1fr)_80px_64px_minmax(110px,1fr)] gap-2'

interface ContractProgramLinesTableProps {
  lines: ContractProgrammableLine[]
  isPending: boolean
  isError: boolean
  onRetry: () => void
  selected: number[]
  onSelectedChange: (next: number[]) => void
}

/**
 * The "Programma" dialog's row-selection table (spec 0095 AC-060): every
 * REVENUE line of the contract's offer, with product/category/quantity/UM;
 * a line already occupied by another work order is shown but its checkbox
 * is disabled, with the occupying commessa's code alongside it. Structure
 * (header grid + row map) mirrors `QuoteLinesReadOnlyList` — copied, not
 * reused, because that component is fixed-width read-only with no selection
 * and no category column.
 */
export function ContractProgramLinesTable({
  lines,
  isPending,
  isError,
  onRetry,
  selected,
  onSelectedChange,
}: ContractProgramLinesTableProps) {
  const { t } = useTranslation()

  const toggleLine = (lineId: number, checked: boolean) => {
    onSelectedChange(checked ? [...selected, lineId] : selected.filter((id) => id !== lineId))
  }

  if (isPending) {
    return (
      <div className="flex flex-col gap-1.5">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-8 w-full" />
        ))}
      </div>
    )
  }

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2 rounded-lg border bg-surface p-3">
        <p className="text-xs text-destructive">{t('contracts.actions.programDialog.linesLoadError')}</p>
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (lines.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('contracts.actions.programDialog.linesEmpty')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border bg-surface">
      <div className="min-w-[560px] text-xs">
        <div className={`${LINES_GRID_CLASS} items-center border-b bg-muted/40 px-2 py-1.5 font-medium text-muted-foreground`}>
          <span className="sr-only">{t('contracts.actions.programDialog.lineSelectHeader')}</span>
          <span>{t('contracts.actions.programDialog.lineProductHeader')}</span>
          <span>{t('contracts.actions.programDialog.lineCategoryHeader')}</span>
          <span>{t('contracts.actions.programDialog.lineQuantityHeader')}</span>
          <span>{t('contracts.actions.programDialog.lineUnitOfMeasureHeader')}</span>
          <span>{t('contracts.actions.programDialog.lineStatusHeader')}</span>
        </div>
        {lines.map((line) => {
          const occupied = line.work_order !== null
          return (
            <div key={line.id} className={`${LINES_GRID_CLASS} items-center border-b px-2 py-1.5 last:border-b-0`}>
              <Checkbox
                checked={selected.includes(line.id)}
                disabled={occupied}
                onCheckedChange={(next) => toggleLine(line.id, next === true)}
                aria-label={t('contracts.actions.programDialog.lineSelectLabel', {
                  name: line.product?.name ?? line.id,
                })}
              />
              <span className="truncate">{line.product?.name ?? '—'}</span>
              <span className="truncate text-muted-foreground">{line.product?.category?.name ?? '—'}</span>
              <span className="tabular-nums">{formatQuoteAmount(Number(line.quantity))}</span>
              <span className="truncate text-muted-foreground">{line.unit_of_measure?.symbol ?? '—'}</span>
              <span className="truncate text-muted-foreground">
                {occupied
                  ? t('contracts.actions.programDialog.lineOccupied', { code: line.work_order?.code })
                  : null}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
