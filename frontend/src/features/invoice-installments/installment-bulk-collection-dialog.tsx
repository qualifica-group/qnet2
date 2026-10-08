import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { BulkCollectionTarget } from '@/features/invoice-installments/installment-bulk-collection'
import { useInstallmentBulkCollectionForm } from '@/features/invoice-installments/use-installment-bulk-collection-form'

interface BulkCollectionFormProps {
  targets: readonly BulkCollectionTarget[]
  onClose: () => void
  onSaved: () => void
}

function BulkCollectionForm({ targets, onClose, onSaved }: BulkCollectionFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending, totalCollected, totalSelected, canSave } = useInstallmentBulkCollectionForm(
    targets,
    () => {
      onSaved()
      onClose()
    },
  )

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex min-h-0 flex-col">
        <div className="border-b px-4 py-3">
          <FormField
            control={form.control}
            name="collected_at"
            render={({ field }) => (
              <FormItem className="max-w-48">
                <FormLabel>{t('invoiceInstallments.bulkCollection.collectedAt')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <ul className="flex max-h-[55vh] flex-col divide-y overflow-y-auto">
          {targets.map((target, index) => (
            <li key={target.id} className="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-start sm:justify-between">
              <div className="min-w-0 text-sm">
                <p className="truncate font-medium">
                  {t('invoiceInstallments.bulkCollection.installment', {
                    invoice: target.invoiceLabel,
                    sequence: target.sequence,
                  })}
                </p>
                <p className="text-xs text-muted-foreground">
                  {t('invoiceInstallments.bulkCollection.dueDate', { date: formatDate(target.dueDate) })}
                  {' · '}
                  {t('invoiceInstallments.bulkCollection.amount', { amount: formatEuro(target.amount) })}
                </p>
              </div>
              <FormField
                control={form.control}
                name={`amounts.${index}`}
                render={({ field }) => (
                  <FormItem className="w-full sm:w-44">
                    <FormLabel className="sr-only">{t('invoiceInstallments.bulkCollection.collectedAmount')}</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        step="0.01"
                        min="0"
                        inputMode="decimal"
                        placeholder={t('invoiceInstallments.bulkCollection.collectedAmount')}
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
              <dt className="text-xs text-muted-foreground">{t('invoiceInstallments.bulkCollection.totalCollected')}</dt>
              <dd className="font-semibold tabular-nums" aria-live="polite">
                {formatEuro(totalCollected)}
              </dd>
            </div>
            <div>
              <dt className="text-xs text-muted-foreground">{t('invoiceInstallments.bulkCollection.totalSelected')}</dt>
              <dd className="tabular-nums">{formatEuro(totalSelected)}</dd>
            </div>
          </dl>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" size="sm" onClick={onClose}>
              {t('invoiceInstallments.bulkCollection.cancel')}
            </Button>
            <Button type="submit" size="sm" disabled={!canSave}>
              {t(isPending ? 'invoiceInstallments.bulkCollection.saving' : 'invoiceInstallments.bulkCollection.save')}
            </Button>
          </div>
        </div>
      </form>
    </Form>
  )
}

interface InstallmentBulkCollectionDialogProps {
  /** The selected installments; empty closes the dialog. */
  targets: readonly BulkCollectionTarget[]
  onClose: () => void
  /** Called after a successful save (the caller clears the selection and refreshes its grid). */
  onSaved: () => void
}

/** "Incasso multiplo" (spec 0198): one amount per selected installment, one collection date. */
export function InstallmentBulkCollectionDialog({ targets, onClose, onSaved }: InstallmentBulkCollectionDialogProps) {
  const { t } = useTranslation()
  const open = targets.length > 0
  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b bg-surface px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('invoiceInstallments.bulkCollection.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('invoiceInstallments.bulkCollection.description')}</DialogDescription>
        </DialogHeader>
        {open ? <BulkCollectionForm targets={targets} onClose={onClose} onSaved={onSaved} /> : null}
      </DialogContent>
    </Dialog>
  )
}
