import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import { usePurchaseInvoiceDetailsForm } from '@/features/purchase-invoices/use-purchase-invoice-editor-details-form'

function DetailsForm({ invoice, onClose }: { invoice: PurchaseInvoice; onClose: () => void }) {
  const { t } = useTranslation()
  // The server decides: a registered invoice only lets holders of purchase-invoices.downgrade change the number.
  const numberLocked = invoice.document_type === 'invoice' && invoice.permissions.actions.downgrade !== true
  const showReceiptNotice = invoice.document_type === 'proforma' && invoice.purchase_orders.length > 0
  const { form, submit, isPending } = usePurchaseInvoiceDetailsForm(invoice, onClose)

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        <FormField
          control={form.control}
          name="document_number"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('purchaseInvoices.details.documentNumber')}</FormLabel>
              <FormControl>
                <Input
                  {...field}
                  disabled={numberLocked}
                  value={field.value ?? ''}
                  onChange={(event) => field.onChange(event.target.value)}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <p className="-mt-1 text-xs text-muted-foreground">
          {t(numberLocked ? 'purchaseInvoices.details.numberLockedHint' : 'purchaseInvoices.details.numberHint')}
        </p>
        {showReceiptNotice ? (
          <p role="note" className="rounded-md border bg-surface px-3 py-2 text-xs text-muted-foreground">
            {t('purchaseInvoices.details.receiptNotice')}
          </p>
        ) : null}
        <FormField
          control={form.control}
          name="notes"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('purchaseInvoices.details.notes')}</FormLabel>
              <FormControl>
                <Textarea rows={3} {...field} value={field.value ?? ''} onChange={(event) => field.onChange(event.target.value)} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            {t('purchaseInvoices.details.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {t(isPending ? 'purchaseInvoices.details.saving' : 'purchaseInvoices.details.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface PurchaseInvoiceDetailsDialogProps {
  invoice: PurchaseInvoice
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Supplier document number (a filled one turns a proforma into an invoice) and notes. */
export function PurchaseInvoiceDetailsDialog({ invoice, open, onOpenChange }: PurchaseInvoiceDetailsDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('purchaseInvoices.details.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('purchaseInvoices.details.description')}</DialogDescription>
        </DialogHeader>
        <DetailsForm key={invoice.id} invoice={invoice} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}
