import type { ReactNode } from 'react'

/** What a summary row shows when the value it recaps is not set yet. */
export const EMPTY_VALUE = '—'

/** The summary list's own chrome, so every side-column recap renders identical rows. */
export const SUMMARY_LIST_CLASS = 'min-w-0 divide-y divide-border/60'

/** One `label / value` row of a side-column summary list. */
export function SummaryRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-col gap-0.5 py-2">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="truncate text-sm font-medium text-foreground">{children}</dd>
    </div>
  )
}
