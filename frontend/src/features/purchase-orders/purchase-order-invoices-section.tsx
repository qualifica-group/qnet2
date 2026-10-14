import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { useAbilities } from '@/features/auth/use-abilities'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import type { PurchaseOrder, PurchaseOrderInvoiceRef } from '@/features/purchase-orders/types'
import { formatDate } from '@/lib/formatting/date-display'

/** Link to the passive invoice when the actor may view them, plain text otherwise. */
export function PurchaseInvoiceLink({ invoiceId, children }: { invoiceId: number; children: ReactNode }) {
  const { can } = useAbilities()

  if (!can(`${PURCHASE_INVOICES_DOMAIN}.view`)) {
    return <>{children}</>
  }

  return (
    <Link to={`/${PURCHASE_INVOICES_DOMAIN}/${invoiceId}`} className="font-medium hover:underline">
      {children}
    </Link>
  )
}

/** One chip per document: type, number (when registered) and date, e.g. "Fattura FT-7 · 10/10/2026". */
function InvoiceItem({ invoice }: { invoice: PurchaseOrderInvoiceRef }) {
  const { t } = useTranslation()

  return (
    <li>
      <Badge variant="secondary" className="gap-1.5">
        <PurchaseInvoiceLink invoiceId={invoice.id}>
          {t(`purchaseOrderActions.invoices.type.${invoice.document_type}`)}
          {invoice.document_number ? <> <span className="font-mono">{invoice.document_number}</span></> : null}
        </PurchaseInvoiceLink>
        {invoice.document_date ? <span className="font-normal text-muted-foreground">{formatDate(invoice.document_date)}</span> : null}
      </Badge>
    </li>
  )
}

/** Passive invoices and proformas linked to the ODA (spec 0255 D-4). */
export function PurchaseOrderInvoicesSection({ order }: { order: PurchaseOrder }) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-3 rounded-lg border bg-card p-3" aria-label={t('purchaseOrderActions.invoices.section')}>
      <h2 className="text-base font-semibold">{t('purchaseOrderActions.invoices.section')}</h2>
      {order.purchase_invoices.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('purchaseOrderActions.invoices.empty')}</p>
      ) : (
        <ul className="flex flex-wrap gap-1.5">
          {order.purchase_invoices.map((invoice) => (
            <InvoiceItem key={invoice.id} invoice={invoice} />
          ))}
        </ul>
      )}
    </section>
  )
}
