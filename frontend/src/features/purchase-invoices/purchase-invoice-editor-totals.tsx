import { useTranslation } from 'react-i18next'
import { fromCents, type InvoiceTotalsCents } from '@/features/invoices/invoice-amounts'
import { formatEuro } from '@/features/invoices/invoice-format'

/** Imponibile / IVA / Totale of the document: the sum of the rows (client preview; the server recomputes). */
export function PurchaseInvoiceEditorTotals({ totals }: { totals: InvoiceTotalsCents }) {
  const { t } = useTranslation()

  return (
    <dl
      aria-label={t('purchaseInvoices.editor.sections.totals')}
      className="grid grid-cols-3 gap-2 rounded-lg border bg-surface p-3 text-xs"
    >
      <div>
        <dt className="text-muted-foreground">{t('purchaseInvoices.editor.totals.net')}</dt>
        <dd className="text-sm font-medium tabular-nums">{formatEuro(fromCents(totals.net))}</dd>
      </div>
      <div>
        <dt className="text-muted-foreground">{t('purchaseInvoices.editor.totals.vat')}</dt>
        <dd className="text-sm font-medium tabular-nums">{formatEuro(fromCents(totals.vat))}</dd>
      </div>
      <div>
        <dt className="text-muted-foreground">{t('purchaseInvoices.editor.totals.total')}</dt>
        <dd className="text-base font-semibold tabular-nums">{formatEuro(fromCents(totals.total))}</dd>
      </div>
    </dl>
  )
}
