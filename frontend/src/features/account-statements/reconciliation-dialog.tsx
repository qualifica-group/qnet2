import { useFormContext } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { ReconciliationFormValues } from '@/features/account-statements/reconciliation-schema'
import { useReconciliationForm } from '@/features/account-statements/use-reconciliation-form'
import { useFinancialSubAccounts } from '@/features/treasury-movements/use-sub-account-options'

const FORM_ID = 'bank-reconciliation-form'
const NOTE_ROWS = 2

/** Financial sub-account the adjustment is booked on: the same list the treasury movement dialog offers. */
function FinancialAccountField() {
  const { t } = useTranslation()
  const { control } = useFormContext<ReconciliationFormValues>()
  const options = useFinancialSubAccounts()

  return (
    <FormField
      control={control}
      name="treasury_type_id"
      render={({ field }) => (
        <FormItem className="sm:col-span-2">
          <FormLabel required>{t('accountStatements.reconciliation.form.adjustmentAccount')}</FormLabel>
          <Select value={field.value} onValueChange={field.onChange}>
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t('accountStatements.reconciliation.form.pick')} />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {options.map((option) => (
                <SelectItem key={option.id} value={String(option.id)}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

interface ReconciliationFormProps {
  accountId: number
  onSaved: () => void
  onClose: () => void
}

function ReconciliationForm({ accountId, onSaved, onClose }: ReconciliationFormProps) {
  const { t } = useTranslation()
  const { form, preview, difference, showAdjustment, adjustmentChecked, isSubmitting, submit } =
    useReconciliationForm(accountId, onSaved)

  return (
    <>
      <div className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto px-0.5">
        <Form {...form}>
          <form id={FORM_ID} onSubmit={(event) => void submit(event)} noValidate className="grid gap-3 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="statement_date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('accountStatements.reconciliation.form.date')}</FormLabel>
                  <FormControl>
                    <Input {...field} type="date" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="bank_balance"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('accountStatements.reconciliation.form.bankBalance')}</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="decimal" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="note"
              render={({ field }) => (
                <FormItem className="sm:col-span-2">
                  <FormLabel>{t('accountStatements.reconciliation.form.note')}</FormLabel>
                  <FormControl>
                    <Textarea {...field} rows={NOTE_ROWS} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            {showAdjustment ? (
              <FormField
                control={form.control}
                name="create_adjustment"
                render={({ field }) => (
                  <FormItem className="sm:col-span-2">
                    <label className="flex items-center gap-1.5 text-sm">
                      <input
                        type="checkbox"
                        className="size-3.5 accent-primary"
                        checked={field.value}
                        onChange={(event) => field.onChange(event.target.checked)}
                      />
                      {t('accountStatements.reconciliation.form.createAdjustment')}
                    </label>
                  </FormItem>
                )}
              />
            ) : null}
            {adjustmentChecked ? <FinancialAccountField /> : null}
          </form>
        </Form>
        <section
          aria-label={t('accountStatements.reconciliation.form.previewTitle')}
          aria-live="polite"
          className="flex flex-col gap-1 rounded-lg border bg-surface p-3 text-sm"
        >
          {preview.isError ? (
            <p role="alert" className="text-destructive">{t('accountStatements.reconciliation.form.previewError')}</p>
          ) : preview.data ? (
            <dl className="grid grid-cols-2 gap-x-3 gap-y-1">
              <dt className="text-muted-foreground">{t('accountStatements.reconciliation.form.reconciledBalance')}</dt>
              <dd className="text-right font-medium tabular-nums">{formatEuro(preview.data.reconciled_balance)}</dd>
              <dt className="text-muted-foreground">{t('accountStatements.reconciliation.form.unreconciledCount')}</dt>
              <dd className="text-right tabular-nums">{preview.data.unreconciled_count}</dd>
              <dt className="text-muted-foreground">{t('accountStatements.reconciliation.form.difference')}</dt>
              <dd className="text-right font-semibold tabular-nums">{difference === null ? '-' : formatEuro(difference)}</dd>
            </dl>
          ) : (
            <p className="text-muted-foreground">{t('accountStatements.reconciliation.form.previewHint')}</p>
          )}
        </section>
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" className="bg-card" onClick={onClose}>
          {t('accountStatements.reconciliation.form.cancel')}
        </Button>
        <Button type="submit" form={FORM_ID} disabled={isSubmitting}>
          {isSubmitting ? t('accountStatements.reconciliation.form.saving') : t('accountStatements.reconciliation.form.save')}
        </Button>
      </DialogFooter>
    </>
  )
}

interface ReconciliationDialogProps {
  accountId: number
  open: boolean
  onClose: () => void
}

/** "New reconciliation": bank balance at a date against the ticked balance, with an optional adjustment. */
export function ReconciliationDialog({ accountId, open, onClose }: ReconciliationDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle>{t('accountStatements.reconciliation.form.title')}</DialogTitle>
          <DialogDescription>{t('accountStatements.reconciliation.form.hint')}</DialogDescription>
        </DialogHeader>
        {open ? <ReconciliationForm accountId={accountId} onSaved={onClose} onClose={onClose} /> : null}
      </DialogContent>
    </Dialog>
  )
}
