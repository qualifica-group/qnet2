import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Skeleton } from '@/components/ui/skeleton'
import { LINE_TABLE_SCROLL_CLASS } from '@/components/record-form/layout'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import {
  ContractProgramLinesToolbar,
  type ContractProgramLinesToolbarProps,
} from '@/features/contracts/contract-program-lines-toolbar'
import type { ContractProgrammableLine } from '@/features/contracts/types'

/** Checkbox / product / category / quantity / UM / group. */
const LINES_GRID_CLASS = 'grid grid-cols-[28px_minmax(140px,1.4fr)_minmax(90px,1fr)_64px_48px_minmax(96px,0.8fr)] gap-2'

interface ContractProgramLinesPanelProps {
  lines: ContractProgrammableLine[]
  isPending: boolean
  isError: boolean
  onRetry: () => void
  selected: number[]
  onSelectedChange: (next: number[]) => void
  /** Index of the group holding the line, `null` when free. */
  groupOfLine: (lineId: number) => number | null
  toolbar: Omit<ContractProgramLinesToolbarProps, 'selectedCount'>
}

/**
 * Left-hand panel of the "Programma" dialog (spec 0215 D-4): every REVENUE
 * line of the contract's offer with a selection checkbox and the group it
 * was assigned to ("G1", text and not just colour); a line already in
 * another work order is shown, disabled, with that commessa's code.
 */
export function ContractProgramLinesPanel({
  lines,
  isPending,
  isError,
  onRetry,
  selected,
  onSelectedChange,
  groupOfLine,
  toolbar,
}: ContractProgramLinesPanelProps) {
  const { t } = useTranslation()
  const freeIds = lines.filter((line) => line.work_order === null).map((line) => line.id)
  const selectedFree = freeIds.filter((id) => selected.includes(id))

  const toggleLine = (lineId: number, checked: boolean) => {
    onSelectedChange(checked ? [...selected, lineId] : selected.filter((id) => id !== lineId))
  }

  const toggleAllFree = (checked: boolean) => onSelectedChange(checked ? freeIds : [])

  return (
    <section
      aria-label={t('contracts.actions.programDialog.linesLabel')}
      className="flex min-w-0 flex-col gap-3 self-start rounded-lg border bg-surface p-3"
    >
      <h3 className="text-sm font-semibold">{t('contracts.actions.programDialog.linesLabel')}</h3>
      <ContractProgramLinesToolbar {...toolbar} selectedCount={selectedFree.length} />
      <p className="text-xs text-muted-foreground" aria-live="polite">
        {selectedFree.length > 0
          ? t('contracts.actions.programDialog.selectedCount', { count: selectedFree.length })
          : t('contracts.actions.programDialog.toolbarHint')}
      </p>

      {isPending ? (
        <div className="flex flex-col gap-1.5">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-8 w-full" />
          ))}
        </div>
      ) : isError ? (
        <div className="flex flex-col items-start gap-2 rounded-lg border bg-card p-3">
          <p className="text-xs text-destructive">{t('contracts.actions.programDialog.linesLoadError')}</p>
          <Button type="button" variant="outline" size="xs" className="bg-card" onClick={onRetry}>
            {t('common.retry')}
          </Button>
        </div>
      ) : lines.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('contracts.actions.programDialog.linesEmpty')}</p>
      ) : (
        <div className={cn(LINE_TABLE_SCROLL_CLASS, 'bg-card')}>
          {/* Tracks + gaps + the rows' `px-2`: narrower, the header's tint stops mid-row when scrolled. */}
          <div className="min-w-[540px] text-xs">
            <div className={`${LINES_GRID_CLASS} items-center border-b bg-muted/40 px-2 py-1.5 font-medium text-muted-foreground`}>
              <Checkbox
                checked={freeIds.length > 0 && selectedFree.length === freeIds.length ? true : selectedFree.length > 0 ? 'indeterminate' : false}
                disabled={freeIds.length === 0}
                onCheckedChange={(next) => toggleAllFree(next === true)}
                aria-label={t('contracts.actions.programDialog.selectAllFree')}
              />
              <span>{t('contracts.actions.programDialog.lineProductHeader')}</span>
              <span>{t('contracts.actions.programDialog.lineCategoryHeader')}</span>
              <span>{t('contracts.actions.programDialog.lineQuantityHeader')}</span>
              <span>{t('contracts.actions.programDialog.lineUnitOfMeasureHeader')}</span>
              <span>{t('contracts.actions.programDialog.lineStatusHeader')}</span>
            </div>
            {lines.map((line) => {
              const occupied = line.work_order !== null
              const groupIndex = groupOfLine(line.id)
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
                    {occupied ? (
                      t('contracts.actions.programDialog.lineOccupied', { code: line.work_order?.code })
                    ) : groupIndex !== null ? (
                      <Badge
                        variant="secondary"
                        aria-label={t('contracts.actions.programDialog.groupName', { number: groupIndex + 1 })}
                      >
                        {t('contracts.actions.programDialog.groupShort', { number: groupIndex + 1 })}
                      </Badge>
                    ) : (
                      '—'
                    )}
                  </span>
                </div>
              )
            })}
          </div>
        </div>
      )}
    </section>
  )
}
