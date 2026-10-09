import { useTranslation } from 'react-i18next'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { PurchaseAmounts } from '@/features/purchase-requests/purchase-request-amounts'

/** Header totals (taxable, VAT, grand total): a preview, the server recomputes on save. */
export function PurchaseRequestTotals({ totals }: { totals: PurchaseAmounts }) {
  const { t } = useTranslation()
  return (
    <dl
      aria-label={t('purchaseRequests.totals.label')}
      className="ml-auto flex flex-wrap justify-end gap-x-6 gap-y-1 rounded-md border bg-surface px-3 py-2 text-xs"
    >
      <div className="flex gap-2">
        <dt className="text-muted-foreground">{t('purchaseRequests.totals.taxable')}</dt>
        <dd className="tabular-nums">{formatQuoteAmount(totals.taxable)}</dd>
      </div>
      <div className="flex gap-2">
        <dt className="text-muted-foreground">{t('purchaseRequests.totals.vat')}</dt>
        <dd className="tabular-nums">{formatQuoteAmount(totals.vat)}</dd>
      </div>
      <div className="flex gap-2">
        <dt className="font-medium">{t('purchaseRequests.totals.grandTotal')}</dt>
        <dd className="font-semibold tabular-nums">{formatQuoteAmount(totals.total)}</dd>
      </div>
    </dl>
  )
}
