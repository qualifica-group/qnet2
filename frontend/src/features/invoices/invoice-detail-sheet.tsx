import { useTranslation } from 'react-i18next'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { INVOICES_DOMAIN } from '@/features/invoices/api'
import { InvoiceDetailView } from '@/features/invoices/invoice-detail'

interface InvoiceDetailSheetProps {
  invoiceId: number | null
  onClose: () => void
  onChanged: () => void
}

/** "Visualizza" row action: the document in a resizable side sheet, without leaving the list. */
export function InvoiceDetailSheet({ invoiceId, onClose, onChanged }: InvoiceDetailSheetProps) {
  const { t } = useTranslation()
  return (
    <Sheet open={invoiceId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey={`sheet-width:${INVOICES_DOMAIN}`}>
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{t('invoices.title')}</SheetTitle>
          <SheetDescription>{t('invoices.subtitle')}</SheetDescription>
        </SheetHeader>
        {invoiceId !== null ? <InvoiceDetailView invoiceId={invoiceId} onChanged={onChanged} /> : null}
      </SheetContent>
    </Sheet>
  )
}
