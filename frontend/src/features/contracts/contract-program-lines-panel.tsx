import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  ContractProgramLinesToolbar,
  type ContractProgramLinesToolbarProps,
} from '@/features/contracts/contract-program-lines-toolbar'
import { ContractProgramLinesFilterBar } from '@/features/contracts/contract-program-lines-filter-bar'
import { ContractProgramLinesTable } from '@/features/contracts/contract-program-lines-table'
import { useContractProgramLinesFilter } from '@/features/contracts/use-contract-program-lines-filter'
import type { ContractProgrammableLine } from '@/features/contracts/types'

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

function freeIdsOf(lines: ContractProgrammableLine[]): number[] {
  return lines.filter((line) => line.work_order === null).map((line) => line.id)
}

/**
 * Left-hand panel of the "Programma" dialog (spec 0215 D-4): every REVENUE
 * line of the contract's offer with a selection checkbox and the group it
 * was assigned to ("G1", text and not just colour); a line already in
 * another work order is shown, disabled, with that commessa's code. A search
 * and a category filter narrow the table without touching the selection.
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
  const prefix = 'contracts.actions.programDialog'
  const filter = useContractProgramLinesFilter(lines)
  const selectedFree = freeIdsOf(lines).filter((id) => selected.includes(id))
  // The header checkbox acts on the free lines the filter shows; hidden selected lines stay selected.
  const visibleFreeIds = freeIdsOf(filter.visibleLines)
  const selectedVisibleFree = visibleFreeIds.filter((id) => selected.includes(id))

  const toggleLine = (lineId: number, checked: boolean) =>
    onSelectedChange(checked ? [...selected, lineId] : selected.filter((id) => id !== lineId))

  const toggleAllVisibleFree = (checked: boolean) =>
    onSelectedChange(
      checked
        ? Array.from(new Set([...selected, ...visibleFreeIds]))
        : selected.filter((id) => !visibleFreeIds.includes(id)),
    )

  const allChecked =
    visibleFreeIds.length > 0 && selectedVisibleFree.length === visibleFreeIds.length
      ? true
      : selectedVisibleFree.length > 0
        ? 'indeterminate'
        : false

  return (
    <section
      aria-label={t(`${prefix}.linesLabel`)}
      className="flex min-w-0 flex-col gap-3 self-start rounded-lg border bg-surface p-3"
    >
      <h3 className="text-sm font-semibold">{t(`${prefix}.linesLabel`)}</h3>
      <ContractProgramLinesToolbar {...toolbar} selectedCount={selectedFree.length} />
      <p className="text-xs text-muted-foreground" aria-live="polite">
        {selectedFree.length > 0
          ? t(`${prefix}.selectedCount`, { count: selectedFree.length })
          : t(`${prefix}.toolbarHint`)}
      </p>

      {isPending ? (
        <div className="flex flex-col gap-1.5">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-8 w-full" />
          ))}
        </div>
      ) : isError ? (
        <div className="flex flex-col items-start gap-2 rounded-lg border bg-card p-3">
          <p className="text-xs text-destructive">{t(`${prefix}.linesLoadError`)}</p>
          <Button type="button" variant="outline" size="xs" className="bg-card" onClick={onRetry}>
            {t('common.retry')}
          </Button>
        </div>
      ) : lines.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t(`${prefix}.linesEmpty`)}</p>
      ) : (
        <>
          <ContractProgramLinesFilterBar
            search={filter.search}
            onSearchChange={filter.setSearch}
            category={filter.category}
            onCategoryChange={filter.setCategory}
            categories={filter.categories}
            hasUncategorized={filter.hasUncategorized}
          />
          {filter.visibleLines.length === 0 ? (
            <p className="text-xs text-muted-foreground">{t(`${prefix}.linesNoMatch`)}</p>
          ) : (
            <ContractProgramLinesTable
              lines={filter.visibleLines}
              selected={selected}
              onToggleLine={toggleLine}
              allChecked={allChecked}
              allDisabled={visibleFreeIds.length === 0}
              onToggleAll={toggleAllVisibleFree}
              groupOfLine={groupOfLine}
            />
          )}
        </>
      )}
    </section>
  )
}
