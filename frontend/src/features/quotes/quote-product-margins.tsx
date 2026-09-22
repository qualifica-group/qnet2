import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Calculator } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { ProductMarginRow } from '@/features/quotes/quote-product-margins-calc'

interface QuoteProductMarginsProps {
  rows: ProductMarginRow[]
  genericCostNet: number
}

/**
 * "Margine per prodotto" block (spec 0144 D-6/AC-015): compact table, one row
 * per OFFER product plus a closing "Costi generici" row. Hidden entirely
 * when the offer has no product row yet — a quote with only costs has
 * nothing to attribute them to.
 */
export function QuoteProductMargins({ rows, genericCostNet }: QuoteProductMarginsProps) {
  const { t } = useTranslation()
  const titleId = useId()

  if (rows.length === 0) {
    return null
  }

  return (
    <div className="rounded-lg border bg-surface p-3">
      <div id={titleId} className="mb-2 flex items-center gap-1.5 text-xs font-semibold text-foreground">
        <Calculator aria-hidden="true" className="size-3.5" />
        {t('quotes.form.summary.productMargins.title')}
      </div>
      <div className="overflow-x-auto">
        <table aria-labelledby={titleId} className="w-full min-w-[420px] text-xs">
          <thead>
            <tr className="border-b text-muted-foreground">
              <th scope="col" className="py-1 text-left font-medium">
                {t('quotes.form.lineProductHeader')}
              </th>
              <th scope="col" className="py-1 text-right font-medium">
                {t('quotes.columns.revenueNet')}
              </th>
              <th scope="col" className="py-1 text-right font-medium">
                {t('quotes.columns.costNet')}
              </th>
              <th scope="col" className="py-1 text-right font-medium">
                {t('quotes.columns.marginNet')}
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.key} className="border-b last:border-b-0">
                <td className="max-w-0 truncate py-1">
                  {t('quotes.form.costsTab.associatedProductOption', {
                    product: row.productName ?? t('quotes.form.commissions.productFallback'),
                    n: row.rowNumber,
                  })}
                </td>
                <td className="py-1 text-right tabular-nums">{formatQuoteAmount(row.revenueNet)}</td>
                <td className="py-1 text-right tabular-nums">{formatQuoteAmount(row.costNet)}</td>
                <td
                  className={cn(
                    'py-1 text-right font-medium tabular-nums',
                    row.margin < 0 ? 'text-destructive' : undefined,
                  )}
                >
                  {formatQuoteAmount(row.margin)}
                </td>
              </tr>
            ))}
            <tr>
              <td className="py-1 font-medium">{t('quotes.form.summary.productMargins.genericCosts')}</td>
              <td className="py-1 text-right text-muted-foreground">—</td>
              <td className="py-1 text-right tabular-nums">{formatQuoteAmount(genericCostNet)}</td>
              <td className="py-1 text-right text-muted-foreground">—</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  )
}
