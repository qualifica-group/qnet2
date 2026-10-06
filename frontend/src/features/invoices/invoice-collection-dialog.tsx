import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro, parseNumberInput } from '@/features/invoices/invoice-format'
import { useInvoiceCollectionForm } from '@/features/invoices/use-invoice-collection-form'
import type { InvoiceInstallment } from '@/features/invoices/types'

interface CollectionFormProps {
  installment: InvoiceInstallment
  onClose: () => void
  onSaved?: () => void
}

function CollectionForm({ installment, onClose, onSaved }: CollectionFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending } = useInvoiceCollectionForm(installment, () => {
    onSaved?.()
    onClose()
  })

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        <FormField
          control={form.control}
          name="collected_amount"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('invoices.collection.collectedAmount')}</FormLabel>
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
          name="collected_at"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('invoices.collection.collectedAt')}</FormLabel>
              <FormControl>
                <Input type="date" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            {t('invoices.collection.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {t(isPending ? 'invoices.collection.saving' : 'invoices.collection.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface InvoiceCollectionDialogProps {
  installment: InvoiceInstallment | null
  onClose: () => void
  /** Called after a successful save (the caller refreshes its grid). */
  onSaved?: () => void
}

/** "Registra incasso" for one installment; the form remounts per installment via `key`. */
export function InvoiceCollectionDialog({ installment, onClose, onSaved }: InvoiceCollectionDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={installment !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="sm" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('invoices.collection.title')}</DialogTitle>
          {installment ? (
            <DialogDescription className="text-xs">
              {t('invoices.collection.description', {
                sequence: installment.sequence,
                dueDate: formatDate(installment.due_date),
                amount: formatEuro(installment.amount),
              })}
            </DialogDescription>
          ) : null}
        </DialogHeader>
        {installment ? <CollectionForm key={installment.id} installment={installment} onClose={onClose} onSaved={onSaved} /> : null}
      </DialogContent>
    </Dialog>
  )
}
