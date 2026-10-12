import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro, parseNumberInput } from '@/features/invoices/invoice-format'
import { PurchaseFinancialAccountSelect } from '@/features/purchase-invoice-installments/purchase-financial-account-select'
import { PurchasePaymentResidualFields } from '@/features/purchase-invoice-installments/purchase-payment-residual-fields'
import { usePurchasePaymentForm } from '@/features/purchase-invoice-installments/use-purchase-payment-form'
import type { PurchaseInstallmentDetail } from '@/features/purchase-invoice-installments/types'

interface PaymentFormProps {
  detail: PurchaseInstallmentDetail
  onClose: () => void
  onSaved: () => void
}

function PaymentForm({ detail, onClose, onSaved }: PaymentFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending, isPartial, canSpread, residualMode, residualAmount } = usePurchasePaymentForm(
    detail,
    () => {
      onSaved()
      onClose()
    },
  )
  const accountLabel = t('purchaseInvoiceInstallments.payment.financialAccount')

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        <FormField
          control={form.control}
          name="paid_amount"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('purchaseInvoiceInstallments.payment.paidAmount')}</FormLabel>
              <FormControl>
                <Input
                  type="number"
                  step="0.01"
                  inputMode="decimal"
                  name={field.name}
                  ref={field.ref}
                  onBlur={field.onBlur}
                  value={Number.isNaN(field.value) ? '' : field.value}
                  onChange={(event) => field.onChange(parseNumberInput(event.target.value, Number.NaN))}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="paid_at"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('purchaseInvoiceInstallments.payment.paidAt')}</FormLabel>
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
                <PurchaseFinancialAccountSelect
                  value={field.value}
                  onChange={field.onChange}
                  companyId={detail.purchase_invoice.company.id}
                  selected={detail.purchase_invoice.financial_account}
                  label={accountLabel}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        {isPartial ? (
          <PurchasePaymentResidualFields
            form={form}
            residualAmount={residualAmount}
            canSpread={canSpread}
            residualMode={residualMode}
          />
        ) : null}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            {t('purchaseInvoiceInstallments.payment.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {t(isPending ? 'purchaseInvoiceInstallments.payment.saving' : 'purchaseInvoiceInstallments.payment.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface PurchasePaymentDialogProps {
  /** The installment to pay, with its document summary; `null` closes the dialog. */
  detail: PurchaseInstallmentDetail | null
  onClose: () => void
  /** Called after a successful save (the caller refreshes its grid). */
  onSaved: () => void
}

/** "Registra pagamento" for one installment; the form remounts per installment via `key`. */
export function PurchasePaymentDialog({ detail, onClose, onSaved }: PurchasePaymentDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={detail !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="sm" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('purchaseInvoiceInstallments.payment.title')}</DialogTitle>
          {detail ? (
            <DialogDescription className="text-xs">
              {t('purchaseInvoiceInstallments.payment.description', {
                sequence: detail.sequence,
                dueDate: formatDate(detail.due_date),
                amount: formatEuro(detail.amount),
              })}
            </DialogDescription>
          ) : null}
        </DialogHeader>
        {detail ? <PaymentForm key={detail.id} detail={detail} onClose={onClose} onSaved={onSaved} /> : null}
      </DialogContent>
    </Dialog>
  )
}
