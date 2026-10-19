import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

export const TH_LEFT = 'px-2 py-1.5 text-left font-medium'
export const TH_RIGHT = 'px-2 py-1.5 text-right font-medium'
export const TD = 'px-2 py-1.5'
export const TD_MONEY = 'px-2 py-1.5 text-right tabular-nums'

/** Scrollable (375px) compact table shell on the intermediate surface. */
export function InvoiceTableShell({ caption, children }: { caption: string; children: ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-lg border bg-surface">
      <table className="w-full min-w-[720px] text-xs">
        <caption className="sr-only">{caption}</caption>
        {children}
      </table>
    </div>
  )
}

/** Document number: a link-styled button opening the detail sheet when allowed, plain text otherwise. */
export function DocumentNumber({
  label,
  canOpen,
  onOpen,
}: {
  label: string
  canOpen: boolean
  onOpen: () => void
}) {
  return canOpen ? (
    <Button type="button" variant="link" className="h-auto p-0 text-xs font-medium" onClick={onOpen}>
      {label}
    </Button>
  ) : (
    <span className="font-medium">{label}</span>
  )
}

export function EmptyTableMessage({ message }: { message: string }) {
  return <p className="rounded-lg border bg-surface px-3 py-2 text-xs text-muted-foreground">{message}</p>
}

/** Totals row: label spans the leading columns, then one cell per entry (empty string = blank). */
export function TotalsRow({ labelSpan, cells }: { labelSpan: number; cells: string[] }) {
  const { t } = useTranslation()
  return (
    <tr className="border-t bg-muted/40 font-semibold">
      <th scope="row" colSpan={labelSpan} className={cn(TD, 'text-left')}>
        {t('workOrders.invoices.totals')}
      </th>
      {cells.map((cell, index) => (
        <td key={index} className={TD_MONEY}>
          {cell}
        </td>
      ))}
    </tr>
  )
}
