import { useState } from 'react'
import { ReceiptText } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { formatDate } from '@/lib/formatting/date-display'
import { PaymentStatusIndicator } from '@/features/invoices/invoice-status-badges'
import { formatEuro } from '@/features/invoices/invoice-format'
import { PurchaseInvoiceDetailSheet } from '@/features/purchase-invoices/purchase-invoice-detail-sheet'
import type { SettlementPurchaseInvoice } from '@/features/commission-settlements/types'

interface SettlementPurchaseInvoiceSectionProps {
  invoice: SettlementPurchaseInvoice
  /** Called when the invoice changed inside its sheet (payments), so the statement refreshes. */
  onChanged: () => void
}

/**
 * "Fattura passiva": the invoice the approval created, with the payment state
 * (the statement's own: it is never stored on it). The passive invoices module
 * has no per-record route, so "Apri fattura" shows the record in its own sheet.
 */
export function SettlementPurchaseInvoiceSection({ invoice, onChanged }: SettlementPurchaseInvoiceSectionProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <RecordSection
      title={t('commissionSettlements.purchaseInvoice.title')}
      icon={<ReceiptText />}
      action={
        <Button variant="secondary" size="xs" onClick={() => setOpen(true)}>
          {t('commissionSettlements.purchaseInvoice.open', { number: invoice.document_number })}
        </Button>
      }
    >
      <RecordFieldList>
        <RecordField label={t('commissionSettlements.purchaseInvoice.number')}>{invoice.document_number}</RecordField>
        <RecordField label={t('commissionSettlements.purchaseInvoice.date')}>{formatDate(invoice.document_date)}</RecordField>
        {invoice.payable_amount !== undefined ? (
          <RecordField label={t('commissionSettlements.purchaseInvoice.payable')}>{formatEuro(invoice.payable_amount)}</RecordField>
        ) : null}
        {invoice.paid_amount !== undefined ? (
          <RecordField label={t('commissionSettlements.purchaseInvoice.paid')}>{formatEuro(invoice.paid_amount)}</RecordField>
        ) : null}
        {invoice.residual_amount !== undefined ? (
          <RecordField label={t('commissionSettlements.purchaseInvoice.residual')}>{formatEuro(invoice.residual_amount)}</RecordField>
        ) : null}
        <RecordField label={t('commissionSettlements.purchaseInvoice.paymentStatus')}>
          <PaymentStatusIndicator status={invoice.payment_status} />
        </RecordField>
      </RecordFieldList>
      <PurchaseInvoiceDetailSheet invoiceId={open ? invoice.id : null} onClose={() => setOpen(false)} onChanged={onChanged} />
    </RecordSection>
  )
}
