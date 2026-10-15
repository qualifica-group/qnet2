import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Skeleton } from '@/components/ui/skeleton'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { MultiProgrammableLinesData } from '@/features/contracts/types'

interface ContractMultiProgramLinesPanelProps {
  data: MultiProgrammableLinesData | undefined
  isPending: boolean
  isError: boolean
  /** The server's explanation when it refuses the selected contracts (422): shown instead of the retry. */
  refusal?: string
  onRetry: () => void
  selected: number[]
  selectableIds: Set<number>
  onSelectedChange: (next: number[]) => void
  /** Id of the element carrying the 422 / hint text, for `aria-describedby`. */
  describedBy?: string
}

/**
 * Left panel of "Programma commessa unica" (spec 0267 D-2): lines grouped by
 * contract (offer code + customer). A line is tickable only when the server
 * flagged it eligible AND it is in the branch of the first pick; every other
 * line stays visible, disabled, with the reason as text (not colour alone).
 */
export function ContractMultiProgramLinesPanel({
  data,
  isPending,
  isError,
  refusal,
  onRetry,
  selected,
  selectableIds,
  onSelectedChange,
  describedBy,
}: ContractMultiProgramLinesPanelProps) {
  const { t } = useTranslation()
  const prefix = 'contracts.actions.multiProgramDialog'

  const toggle = (lineId: number, checked: boolean) =>
    onSelectedChange(checked ? [...selected, lineId] : selected.filter((id) => id !== lineId))

  if (isPending) {
    return (
      <div className="flex flex-col gap-1.5">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-8 w-full" />
        ))}
      </div>
    )
  }

  if (refusal !== undefined) {
    return (
      <div role="alert" className="rounded-lg border border-destructive/40 bg-card p-3 text-xs text-destructive">
        {refusal}
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div className="flex flex-col items-start gap-2 rounded-lg border bg-card p-3">
        <p className="text-xs text-destructive">{t(`${prefix}.linesLoadError`)}</p>
        <Button type="button" variant="outline" size="xs" className="bg-card" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <div className="flex min-w-0 flex-col gap-3" role="group" aria-describedby={describedBy}>
      {data.contracts.map((contract) => {
        const lines = data.lines.filter((line) => line.contract_id === contract.id)
        const heading = `${contract.quote.code} · ${contract.registry?.name ?? '—'}`
        return (
          <section key={contract.id} aria-label={heading} className="min-w-0 rounded-lg border bg-card">
            <h4 className="truncate border-b bg-muted/40 px-2 py-1.5 text-xs font-semibold">{heading}</h4>
            {lines.length === 0 ? (
              <p className="px-2 py-1.5 text-xs text-muted-foreground">{t(`${prefix}.contractNoLines`)}</p>
            ) : (
              <ul className="divide-y text-xs">
                {lines.map((line) => {
                  const name = line.product?.name ?? String(line.id)
                  const reason = line.work_order
                    ? t(`${prefix}.lineOccupied`, { code: line.work_order.code })
                    : !line.multi_quote_eligible
                      ? t(`${prefix}.lineNotEligible`)
                      : !selectableIds.has(line.id)
                        ? t(`${prefix}.lineOtherBranch`)
                        : null
                  return (
                    <li key={line.id} className="flex items-center gap-2 px-2 py-1.5">
                      <Checkbox
                        id={`multi-line-${line.id}`}
                        checked={selected.includes(line.id)}
                        disabled={!selectableIds.has(line.id)}
                        onCheckedChange={(next) => toggle(line.id, next === true)}
                        aria-label={t('contracts.actions.programDialog.lineSelectLabel', { name })}
                      />
                      <span className="min-w-0 flex-1 truncate">{name}</span>
                      <span className="hidden shrink-0 truncate text-muted-foreground sm:inline">
                        {line.branch_root?.name ?? '—'}
                      </span>
                      <span className="shrink-0 tabular-nums">
                        {formatQuoteAmount(Number(line.quantity))} {line.unit_of_measure?.symbol ?? ''}
                      </span>
                      {reason ? <span className="shrink-0 text-muted-foreground">{reason}</span> : null}
                    </li>
                  )
                })}
              </ul>
            )}
          </section>
        )
      })}
    </div>
  )
}
