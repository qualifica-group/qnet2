import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'

export interface QuoteLineEditorTotals {
  net: number
  vat: number
  gross: number
  /** "Totale offerta", "Totale costi"…: the channel names its own grand total. */
  totalLabel: string
}

interface QuoteLineEditorFrameProps {
  /** The rows pane's title, also its accessible name. */
  title: string
  count: number
  emptyText: string
  /** `null` while there is nothing to total. */
  totals: QuoteLineEditorTotals | null
  /** The catalogue pane; `null` on a read-only field (the rows pane then takes the whole block). */
  catalog: ReactNode | null
  /** The row cards (`<li>` items). */
  children: ReactNode
  /** Beside the title, at the header's end (the detail's edit pencil). */
  headerAction?: ReactNode
}

/**
 * The "catalogo + righe" block (spec 0268 D-1, one block per the user
 * directive of 2026-10-16), shared by every line editor (spec 0270 D-2): a
 * single `bg-surface` frame, the catalogue and the rows split by a hairline —
 * vertical side by side, horizontal once the container is too narrow and they
 * stack (rows first). The row cards stay one rung above it (`bg-card`).
 */
export function QuoteLineEditorFrame({
  title,
  count,
  emptyText,
  totals,
  catalog,
  children,
  headerAction,
}: QuoteLineEditorFrameProps) {
  const { t } = useTranslation()

  return (
    <div className="@container">
      <div className={cn('grid rounded-lg border bg-surface', catalog !== null && '@3xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]')}>
        <section aria-label={title} className="flex min-w-0 flex-col @3xl:order-2">
          <div className="flex items-center gap-2 border-b p-3">
            <p className="min-w-0 flex-1 text-sm font-semibold">
              {title}
              <span className="ml-1.5 font-normal text-muted-foreground">({count})</span>
            </p>
            {/* Pulled into the title's line height: the header keeps its size with or without it. */}
            {headerAction ? <div className="-my-0.5 shrink-0">{headerAction}</div> : null}
          </div>

          {count === 0 ? (
            <p className="m-2 rounded-lg border border-dashed bg-card px-3 py-6 text-center text-xs text-muted-foreground">
              {emptyText}
            </p>
          ) : (
            <ul className="flex flex-col gap-2 p-2">{children}</ul>
          )}

          {totals !== null && count > 0 ? (
            <dl className="mt-auto flex flex-wrap items-baseline justify-end gap-x-4 gap-y-1 border-t px-3 py-2.5 text-xs">
              <div className="flex gap-1.5">
                <dt className="text-muted-foreground">{t('quotes.form.lineNetHeader')}</dt>
                <dd className="tabular-nums">{formatQuoteAmount(totals.net)}</dd>
              </div>
              <div className="flex gap-1.5">
                <dt className="text-muted-foreground">{t('quotes.form.lineVatHeader')}</dt>
                <dd className="tabular-nums">{formatQuoteAmount(totals.vat)}</dd>
              </div>
              <div className="flex gap-1.5 text-sm">
                <dt className="font-medium">{totals.totalLabel}</dt>
                <dd className="font-semibold tabular-nums">{formatQuoteAmount(totals.gross)}</dd>
              </div>
            </dl>
          ) : null}
        </section>

        {catalog === null ? null : (
          <div className="flex min-w-0 flex-col border-t @3xl:order-1 @3xl:border-t-0 @3xl:border-r">{catalog}</div>
        )}
      </div>
    </div>
  )
}
