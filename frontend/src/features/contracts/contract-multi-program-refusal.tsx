import { useTranslation } from 'react-i18next'
import { TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { MultiProgramRefusal } from '@/features/contracts/types'

interface ContractMultiProgramRefusalProps {
  refusal: MultiProgramRefusal
  /** True when dropping the refused contracts still leaves at least two to group. */
  canExclude: boolean
  onExclude: () => void
}

/**
 * The server's refusal of the selected contracts (spec 0267 D-14), shown in
 * place of the lines: a title by reason, the refused contracts one per row
 * (offer code + customer) and the action that drops them from the dialog.
 * Without the structured body it falls back to the server's sentence.
 */
export function ContractMultiProgramRefusal({ refusal, canExclude, onExclude }: ContractMultiProgramRefusalProps) {
  const { t } = useTranslation()
  const prefix = 'contracts.actions.multiProgramDialog.refusal'
  const count = refusal.contracts.length
  const structured = refusal.reason !== null && count > 0

  return (
    <div role="alert" className="flex flex-col gap-3 rounded-lg border border-destructive/40 bg-card p-3">
      <div className="flex items-start gap-2">
        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-destructive" aria-hidden="true" />
        <div className="flex min-w-0 flex-col gap-0.5">
          <p className="text-sm font-semibold">
            {structured ? t(`${prefix}.${refusal.reason}.title`, { count }) : t(`${prefix}.genericTitle`)}
          </p>
          <p className="text-xs text-muted-foreground">
            {structured ? t(`${prefix}.${refusal.reason}.description`) : refusal.message}
          </p>
        </div>
      </div>

      {structured ? (
        <ul className="divide-y rounded-md border bg-background text-xs" aria-label={t(`${prefix}.listLabel`)}>
          {refusal.contracts.map((contract) => (
            <li key={contract.id} className="flex min-w-0 items-center gap-2 px-2 py-1.5">
              <span className="shrink-0 font-medium tabular-nums">{contract.quote_code ?? '—'}</span>
              <span className="min-w-0 flex-1 truncate text-muted-foreground">{contract.registry_name ?? '—'}</span>
            </li>
          ))}
        </ul>
      ) : null}

      {structured ? (
        canExclude ? (
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" size="xs" onClick={onExclude}>
              {t(`${prefix}.exclude`, { count })}
            </Button>
            <span className="text-xs text-muted-foreground">{t(`${prefix}.excludeHint`)}</span>
          </div>
        ) : (
          <p className="text-xs text-muted-foreground">{t(`${prefix}.notEnough`)}</p>
        )
      ) : null}
    </div>
  )
}
