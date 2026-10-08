import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { formatEuro } from '@/features/invoices/invoice-format'
import { resolveFieldAccess } from '@/features/invoice-installments/installment-field-access'
import { useInstallmentEditForm } from '@/features/invoice-installments/use-installment-edit-form'
import { useInstallment } from '@/features/invoice-installments/use-installment-queries'
import type { InstallmentDetail } from '@/features/invoice-installments/types'

const SERVER_ERROR_ID = 'installment-edit-server-error'

function SummaryItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="truncate font-medium text-foreground">{value}</dd>
    </div>
  )
}

interface EditFormProps {
  detail: InstallmentDetail
  onClose: () => void
  onSaved: () => void
}

function EditForm({ detail, onClose, onSaved }: EditFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending } = useInstallmentEditForm(detail, () => {
    onSaved()
    onClose()
  })
  const dueDate = resolveFieldAccess(detail, 'due_date')
  const methodCode = resolveFieldAccess(detail, 'payment_method_code')
  const serverError = form.formState.errors.root?.server?.message
  const canSubmit = dueDate.editable || methodCode.editable

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        <dl className="grid grid-cols-2 gap-2 rounded-lg border border-border bg-surface p-3 text-xs sm:grid-cols-3">
          <SummaryItem
            label={t('invoiceInstallments.edit.summary.invoice')}
            value={`${detail.invoice.number_label} / ${detail.sequence}`}
          />
          <SummaryItem label={t('invoiceInstallments.edit.summary.customer')} value={detail.invoice.customer.name} />
          {detail.amount !== undefined ? (
            <SummaryItem label={t('invoiceInstallments.edit.summary.amount')} value={formatEuro(detail.amount)} />
          ) : null}
          {detail.residual_amount !== undefined ? (
            <SummaryItem label={t('invoiceInstallments.edit.summary.residual')} value={formatEuro(detail.residual_amount)} />
          ) : null}
        </dl>
        {dueDate.visible ? (
          <FormField
            control={form.control}
            name="due_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('invoiceInstallments.edit.dueDate')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} readOnly={!dueDate.editable} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        ) : null}
        {methodCode.visible ? (
          <FormField
            control={form.control}
            name="payment_method_code"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('invoiceInstallments.edit.paymentMethodCode')}</FormLabel>
                <FormControl>
                  <Input {...field} readOnly={!methodCode.editable} autoComplete="off" />
                </FormControl>
                <FormDescription className="text-xs">{t('invoiceInstallments.edit.paymentMethodCodeHint')}</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
        ) : null}
        {serverError ? (
          <p id={SERVER_ERROR_ID} role="alert" className="text-sm text-destructive">
            {serverError}
          </p>
        ) : null}
        {canSubmit ? null : (
          <p className="text-xs text-muted-foreground">{t('invoiceInstallments.edit.readOnlyHint')}</p>
        )}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            {t('invoiceInstallments.edit.cancel')}
          </Button>
          {canSubmit ? (
            <Button type="submit" size="sm" disabled={isPending} aria-describedby={serverError ? SERVER_ERROR_ID : undefined}>
              {t(isPending ? 'invoiceInstallments.edit.saving' : 'invoiceInstallments.edit.save')}
            </Button>
          ) : null}
        </div>
      </form>
    </Form>
  )
}

function EditBody({ installmentId, onClose, onSaved }: { installmentId: number } & Omit<EditFormProps, 'detail'>) {
  const { t } = useTranslation()
  const { data, isError, refetch } = useInstallment(installmentId)

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2 p-4">
        <p className="text-sm text-destructive">{t('invoiceInstallments.edit.loadError')}</p>
        <Button type="button" variant="secondary" size="sm" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!data) {
    return <Skeleton className="m-4 h-40" />
  }
  return <EditForm key={data.id} detail={data} onClose={onClose} onSaved={onSaved} />
}

interface InstallmentEditDialogProps {
  installmentId: number | null
  onClose: () => void
  /** Called after a successful save (the caller refreshes its grid). */
  onSaved: () => void
}

/** "Modifica scadenza": due date and payment method code of one open installment. */
export function InstallmentEditDialog({ installmentId, onClose, onSaved }: InstallmentEditDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={installmentId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('invoiceInstallments.edit.title')}</DialogTitle>
          <DialogDescription className="text-xs">
            {t('invoiceInstallments.edit.description')}
          </DialogDescription>
        </DialogHeader>
        {installmentId !== null ? (
          <EditBody installmentId={installmentId} onClose={onClose} onSaved={onSaved} />
        ) : null}
      </DialogContent>
    </Dialog>
  )
}
