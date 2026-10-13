import { useTranslation } from 'react-i18next'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import { PurchaseInvoiceDetailScreen } from '@/features/purchase-invoices/purchase-invoice-screens'

interface PurchaseInvoiceDetailSheetProps {
  invoiceId: number | null
  onClose: () => void
  onChanged: () => void
}

/** "Visualizza" row action: the document in a resizable side sheet, without leaving the list. */
export function PurchaseInvoiceDetailSheet({ invoiceId, onClose, onChanged }: PurchaseInvoiceDetailSheetProps) {
  const { t } = useTranslation()
  return (
    <Sheet open={invoiceId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey={`sheet-width:${PURCHASE_INVOICES_DOMAIN}`}>
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{t('purchaseInvoiceViews.title')}</SheetTitle>
          <SheetDescription>{t('purchaseInvoiceViews.subtitle')}</SheetDescription>
        </SheetHeader>
        {invoiceId !== null ? (
          <PurchaseInvoiceDetailScreen id={invoiceId} onChanged={onChanged} onSaved={onClose} />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
