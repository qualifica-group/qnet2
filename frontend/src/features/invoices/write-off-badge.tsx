import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES, EmptyCell } from '@/features/table/cell-renderers'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { WriteOffReason } from '@/features/invoices/types'

const REASON_COLORS: Record<WriteOffReason, string> = {
  rounding: BADGE_COLOR_CLASSES.amber,
  bad_debt: BADGE_COLOR_CLASSES.red,
}

/** Type guard for the backend `write_off_reason` value (grid cells receive it untyped). */
function isWriteOffReason(value: unknown): value is WriteOffReason {
  return value === 'rounding' || value === 'bad_debt'
}

interface WriteOffBadgeProps {
  reason: WriteOffReason
  amount?: string | number | null
  /** Optional note, exposed as the tooltip. */
  note?: string | null
  className?: string
}

/** Compact "Abbuono" / "Stralcio" label with the written-off amount, shown beside the Paid badge (spec 0361 D-6). */
export function WriteOffBadge({ reason, amount, note, className }: WriteOffBadgeProps) {
  const { t } = useTranslation()
  const formatted = formatEuro(amount)
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, REASON_COLORS[reason], className)} title={note ?? undefined}>
      {t(`writeOff.reasons.${reason}`)}
      {formatted ? ` ${formatted}` : ''}
    </Badge>
  )
}

/** Grid cell for the read-only `write_off_reason` column: the translated reason, blank when none. */
export function WriteOffReasonCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  return isWriteOffReason(value) ? <span>{t(`writeOff.reasons.${value}`)}</span> : <EmptyCell align="left" />
}

/** Label beside the Paid badge of a grid row, from the row's `write_off_*` columns; nothing when absent. */
export function RowWriteOffBadge({ data }: { data: unknown }) {
  const row = (data ?? {}) as { write_off_reason?: unknown; write_off_amount?: string | number | null }
  return isWriteOffReason(row.write_off_reason) ? (
    <WriteOffBadge reason={row.write_off_reason} amount={row.write_off_amount} className="ml-1" />
  ) : null
}
