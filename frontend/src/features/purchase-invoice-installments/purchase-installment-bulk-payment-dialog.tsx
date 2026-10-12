import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import { PurchaseFinancialAccountSelect } from '@/features/purchase-invoice-installments/purchase-financial-account-select'
import type { BulkPaymentTarget } from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment'
import { usePurchaseInstallmentBulkPaymentForm } from '@/features/purchase-invoice-installments/use-purchase-installment-bulk-payment-form'

interface BulkPaymentFormProps {
  targets: readonly BulkPaymentTarget[]
  onClose: () => void
  onSaved: () => void
}

function BulkPaymentForm({ targets, onClose, onSaved }: BulkPaymentFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending, totalPaid, totalSelected, canSave } = usePurchaseInstallmentBulkPaymentForm(
    targets,
    () => {
      onSaved()
      onClose()
    },
  )
  const accountLabel = t('purchaseInvoiceInstallments.bulkPayment.financialAccount')

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex min-h-0 flex-col">
        <div className="grid gap-3 border-b px-4 py-3 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="paid_at"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('purchaseInvoiceInstallments.bulkPayment.paidAt')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="financial_account_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{accountLabel}</FormLabel>
                <FormControl>
                  <PurchaseFinancialAccountSelect value={field.value} onChange={field.onChange} label={accountLabel} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <ul className="flex max-h-[50vh] flex-col divide-y overflow-y-auto">
          {targets.map((target, index) => (
            <li key={target.id} className="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-start sm:justify-between">
              <div className="min-w-0 text-sm">
                <p className="truncate font-medium">
                  {t('purchaseInvoiceInstallments.bulkPayment.installment', {
                    document: target.documentLabel,
                    sequence: target.sequence,
                  })}
                </p>
                <p className="text-xs text-muted-foreground">
                  {t('purchaseInvoiceInstallments.bulkPayment.dueDate', { date: formatDate(target.dueDate) })}
                  {' · '}
                  {t('purchaseInvoiceInstallments.bulkPayment.amount', { amount: formatEuro(target.amount) })}
                </p>
              </div>
              <FormField
                control={form.control}
                name={`amounts.${index}`}
                render={({ field }) => (
                  <FormItem className="w-full sm:w-44">
                    <FormLabel className="sr-only">{t('purchaseInvoiceInstallments.bulkPayment.paidAmount')}</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        step="0.01"
                        min="0"
                        inputMode="decimal"
                        placeholder={t('purchaseInvoiceInstallments.bulkPayment.paidAmount')}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </li>
          ))}
        </ul>

        <div className="flex flex-col gap-3 border-t bg-surface px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <dl className="flex gap-4 text-sm">
            <div>
              <dt className="text-xs text-muted-foreground">{t('purchaseInvoiceInstallments.bulkPayment.totalPaid')}</dt>
              <dd className="font-semibold tabular-nums" aria-live="polite">
                {formatEuro(totalPaid)}
              </dd>
            </div>
            <div>
              <dt className="text-xs text-muted-foreground">{t('purchaseInvoiceInstallments.bulkPayment.totalSelected')}</dt>
              <dd className="tabular-nums">{formatEuro(totalSelected)}</dd>
            </div>
          </dl>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" size="sm" onClick={onClose}>
              {t('purchaseInvoiceInstallments.bulkPayment.cancel')}
            </Button>
            <Button type="submit" size="sm" disabled={!canSave}>
              {t(isPending ? 'purchaseInvoiceInstallments.bulkPayment.saving' : 'purchaseInvoiceInstallments.bulkPayment.save')}
            </Button>
          </div>
        </div>
      </form>
    </Form>
  )
}

interface PurchaseInstallmentBulkPaymentDialogProps {
  /** The selected installments; empty closes the dialog. */
  targets: readonly BulkPaymentTarget[]
  onClose: () => void
  /** Called after a successful save (the caller clears the selection and refreshes its grid). */
  onSaved: () => void
}

/** "Pagamento multiplo" (spec 0211 D-8): one amount per selected installment, one payment date and account. */
export function PurchaseInstallmentBulkPaymentDialog({
  targets,
  onClose,
  onSaved,
}: PurchaseInstallmentBulkPaymentDialogProps) {
  const { t } = useTranslation()
  const open = targets.length > 0
  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b bg-surface px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('purchaseInvoiceInstallments.bulkPayment.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('purchaseInvoiceInstallments.bulkPayment.description')}</DialogDescription>
        </DialogHeader>
        {open ? <BulkPaymentForm targets={targets} onClose={onClose} onSaved={onSaved} /> : null}
      </DialogContent>
    </Dialog>
  )
}
