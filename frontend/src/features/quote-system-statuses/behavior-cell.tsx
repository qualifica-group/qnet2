import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { cn } from '@/lib/utils'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { EmptyCell } from '@/features/table/cell-renderers'
import type { QuoteSystemStatusBehavior } from '@/features/quote-system-statuses/types'

/** Palette token of each behavior's dot, aligned with the workflow-status group badges. */
const BEHAVIOR_SWATCH_TOKENS: Record<QuoteSystemStatusBehavior, string> = {
  open: 'green',
  pending: 'orange',
  validated: 'violet',
  closed_won: 'emerald',
  closed_lost: 'red',
}

/** The fixed `behavior` as a coloured dot + localized label. */
export function BehaviorCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const behavior = value as QuoteSystemStatusBehavior | null | undefined
  if (!behavior) {
    return <EmptyCell align="left" />
  }
  return (
    <div className="flex h-full items-center gap-2 overflow-hidden px-2">
      <span
        className={cn('size-3 shrink-0 rounded-full border', swatchClassFor(BEHAVIOR_SWATCH_TOKENS[behavior]) ?? 'bg-transparent')}
        aria-hidden="true"
      />
      <span className="truncate">{t(`quoteSystemStatuses.form.behavior.${behavior}`)}</span>
    </div>
  )
}
