import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { LINE_TABLE_SCROLL_CLASS } from '@/components/record-form/layout'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { ContractProgrammableLine } from '@/features/contracts/types'

/** Checkbox / product / category / quantity / UM / group. */
const LINES_GRID_CLASS = 'grid grid-cols-[28px_minmax(140px,1.4fr)_minmax(90px,1fr)_64px_48px_minmax(96px,0.8fr)] gap-2'

interface ContractProgramLinesTableProps {
  /** The lines to show (already filtered). */
  lines: ContractProgrammableLine[]
  selected: number[]
  onToggleLine: (lineId: number, checked: boolean) => void
  /** State of the header checkbox over the shown free lines. */
  allChecked: boolean | 'indeterminate'
  allDisabled: boolean
  onToggleAll: (checked: boolean) => void
  /** Index of the group holding the line, `null` when free. */
  groupOfLine: (lineId: number) => number | null
}

/** The lines table of the "Programma" dialog: a line already in a work order is shown disabled with its code. */
export function ContractProgramLinesTable({
  lines,
  selected,
  onToggleLine,
  allChecked,
  allDisabled,
  onToggleAll,
  groupOfLine,
}: ContractProgramLinesTableProps) {
  const { t } = useTranslation()
  const prefix = 'contracts.actions.programDialog'

  return (
    <div className={cn(LINE_TABLE_SCROLL_CLASS, 'bg-card')}>
      {/* Tracks + gaps + the rows' `px-2`: narrower, the header's tint stops mid-row when scrolled. */}
      <div className="min-w-[540px] text-xs">
        <div className={`${LINES_GRID_CLASS} items-center border-b bg-muted/40 px-2 py-1.5 font-medium text-muted-foreground`}>
          <Checkbox
            checked={allChecked}
            disabled={allDisabled}
            onCheckedChange={(next) => onToggleAll(next === true)}
            aria-label={t(`${prefix}.selectAllFree`)}
          />
          <span>{t(`${prefix}.lineProductHeader`)}</span>
          <span>{t(`${prefix}.lineCategoryHeader`)}</span>
          <span>{t(`${prefix}.lineQuantityHeader`)}</span>
          <span>{t(`${prefix}.lineUnitOfMeasureHeader`)}</span>
          <span>{t(`${prefix}.lineStatusHeader`)}</span>
        </div>
        {lines.map((line) => {
          const occupied = line.work_order !== null
          const groupIndex = groupOfLine(line.id)
          return (
            <div key={line.id} className={`${LINES_GRID_CLASS} items-center border-b px-2 py-1.5 last:border-b-0`}>
              <Checkbox
                checked={selected.includes(line.id)}
                disabled={occupied}
                onCheckedChange={(next) => onToggleLine(line.id, next === true)}
                aria-label={t(`${prefix}.lineSelectLabel`, { name: line.product?.name ?? line.id })}
              />
              {/* Wraps: names differing only at the end ("... 1° Anno" / "... 2° Anno") must be readable to pick. */}
              <span className="min-w-0 break-words">{line.product?.name ?? '—'}</span>
              <span className="min-w-0 break-words text-muted-foreground">{line.product?.category?.name ?? '—'}</span>
              <span className="tabular-nums">{formatQuoteAmount(Number(line.quantity))}</span>
              <span className="truncate text-muted-foreground">{line.unit_of_measure?.symbol ?? '—'}</span>
              <span className="truncate text-muted-foreground">
                {occupied ? (
                  t(`${prefix}.lineOccupied`, { code: line.work_order?.code })
                ) : groupIndex !== null ? (
                  <Badge variant="secondary" aria-label={t(`${prefix}.groupName`, { number: groupIndex + 1 })}>
                    {t(`${prefix}.groupShort`, { number: groupIndex + 1 })}
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
  )
}
