import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'

/*
 * The pieces a line card is made of, shared by the editor's card and the
 * detail's read-only one, so a saved row reads exactly like the open form.
 */

export const LINE_CARD_CLASS = 'flex flex-col gap-2.5 rounded-lg border bg-card p-3 shadow-sm'

/** Tinted strip (ui-design §1-bis: a wash on top of the card): the amounts read as one block under the product. */
export const LINE_CARD_STRIP_CLASS = 'ml-7 flex flex-wrap items-start gap-x-3 gap-y-2 rounded-md bg-muted/40 px-2.5 py-2'

/** Indent of a card's third level, aligned with the product name. */
export const LINE_CARD_FOOTER_CLASS = 'ml-7'

interface QuoteLineCardHeadingProps {
  position: number
  /** The product name, or the editor's own pending/missing text. */
  name: string
  typology?: { name: string; color?: string | null } | null
  code?: string | null
  categoryPath?: string | null
  /** Under the product meta (the editor's product error). */
  note?: ReactNode
  /** Trailing actions at the card's end. */
  children?: ReactNode
}

/** Level 1 of a card: position, product, typology, code and category path, then the actions. */
export function QuoteLineCardHeading({
  position,
  name,
  typology,
  code,
  categoryPath,
  note,
  children,
}: QuoteLineCardHeadingProps) {
  return (
    <div className="flex items-start gap-2">
      <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
        {position}
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
          <span className="min-w-0 text-sm font-medium break-words">{name}</span>
          {typology ? <ProductTypologyBadge name={typology.name} color={typology.color} /> : null}
        </div>
        {code || categoryPath ? (
          <div className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
            {code ? <span className="shrink-0 font-mono">{code}</span> : null}
            {code && categoryPath ? <span aria-hidden="true">·</span> : null}
            {categoryPath ? <span className="truncate" title={categoryPath}>{categoryPath}</span> : null}
          </div>
        ) : null}
        {note}
      </div>
      {children}
    </div>
  )
}

interface QuoteLineAmountsProps {
  net: number
  vat: number
  total: number
}

/** The row's net, VAT and total, at the strip's end. */
export function QuoteLineAmounts({ net, vat, total }: QuoteLineAmountsProps) {
  const { t } = useTranslation()

  return (
    <dl className="ml-auto grid grid-cols-3 gap-x-4 text-right text-xs">
      <dt className="text-muted-foreground">{t('quotes.form.lineNetHeader')}</dt>
      <dt className="text-muted-foreground">{t('quotes.form.lineVatHeader')}</dt>
      <dt className="text-muted-foreground">{t('quotes.form.lineTotalHeader')}</dt>
      <dd className="tabular-nums">{formatQuoteAmount(net)}</dd>
      <dd className="tabular-nums">{formatQuoteAmount(vat)}</dd>
      <dd className="font-semibold tabular-nums">{formatQuoteAmount(total)}</dd>
    </dl>
  )
}
