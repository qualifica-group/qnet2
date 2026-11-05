import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { formatDate } from '@/lib/formatting/date-display'
import { CompensationResidualFields } from '@/features/compensations/compensation-residual-fields'
import { NO_ID } from '@/features/compensations/compensation-math'
import { useCompensationForm } from '@/features/compensations/use-compensation-form'
import type { CompensationTarget } from '@/features/compensations/types'
import { formatEuro } from '@/features/invoices/invoice-format'

const SELECT_CLASS =
  'h-8 w-full rounded-md border border-field-border bg-card px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'

interface CompensationFormProps {
  target: CompensationTarget
  onClose: () => void
}

function CompensationForm({ target, onClose }: CompensationFormProps) {
  const { t } = useTranslation()
  const { form, submit, changeCompany, isPending, candidates, companyId, amountCents, residualDiffers, residualMode } =
    useCompensationForm(target, onClose)
  const receivables = candidates.data?.receivables ?? []
  const payables = candidates.data?.payables ?? []
  const noCandidates = candidates.isSuccess && (receivables.length === 0 || payables.length === 0)

  const companyLabels = {
    placeholder: t('compensations.dialog.companyPlaceholder'),
    searchPlaceholder: t('compensations.dialog.companySearch'),
    empty: t('compensations.dialog.companyEmpty'),
    error: t('compensations.dialog.companyError'),
    clearLabel: t('compensations.dialog.companyClear'),
    triggerLabel: t('compensations.dialog.company'),
    retry: t('compensations.dialog.retry'),
  }

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        {target.companyId === undefined ? (
          <FormField
            control={form.control}
            name="company_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('compensations.dialog.company')}</FormLabel>
                <FormControl>
                  <AsyncPaginatedSelect
                    resource="companies"
                    value={field.value === NO_ID ? null : field.value}
                    onChange={(value) => changeCompany(value ?? NO_ID)}
                    labels={companyLabels}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        ) : null}
        {candidates.isError ? (
          <p role="alert" className="text-xs text-destructive">
            {t('compensations.dialog.candidatesError')}
          </p>
        ) : null}
        {noCandidates ? (
          <p role="status" className="text-xs text-muted-foreground">
            {t('compensations.dialog.noCandidates')}
          </p>
        ) : null}
        <FormField
          control={form.control}
          name="invoice_installment_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('compensations.dialog.receivable')}</FormLabel>
              <FormControl>
                <select
                  className={SELECT_CLASS}
                  name={field.name}
                  ref={field.ref}
                  onBlur={field.onBlur}
                  value={field.value}
                  disabled={companyId === NO_ID || candidates.isPending}
                  onChange={(event) => field.onChange(Number(event.target.value))}
                >
                  <option value={NO_ID}>{t('compensations.dialog.choose')}</option>
                  {receivables.map((candidate) => (
                    <option key={candidate.id} value={candidate.id}>
                      {t('compensations.dialog.option', {
                        document: candidate.invoice.number_label,
                        sequence: candidate.sequence,
                        dueDate: formatDate(candidate.due_date),
                        residual: formatEuro(Number(candidate.residual)),
                      })}
                    </option>
                  ))}
                </select>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="purchase_invoice_installment_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('compensations.dialog.payable')}</FormLabel>
              <FormControl>
                <select
                  className={SELECT_CLASS}
                  name={field.name}
                  ref={field.ref}
                  onBlur={field.onBlur}
                  value={field.value}
                  disabled={companyId === NO_ID || candidates.isPending}
                  onChange={(event) => field.onChange(Number(event.target.value))}
                >
                  <option value={NO_ID}>{t('compensations.dialog.choose')}</option>
                  {payables.map((candidate) => (
                    <option key={candidate.id} value={candidate.id}>
                      {t('compensations.dialog.option', {
                        document: candidate.purchase_invoice.number_label,
                        sequence: candidate.sequence,
                        dueDate: formatDate(candidate.due_date),
                        residual: formatEuro(Number(candidate.residual)),
                      })}
                    </option>
                  ))}
                </select>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <p className="flex items-baseline justify-between rounded-lg border border-border bg-surface px-3 py-2 text-sm">
          <span className="text-muted-foreground">{t('compensations.dialog.amount')}</span>
          <output aria-label={t('compensations.dialog.amount')} className="font-semibold tabular-nums">
            {formatEuro(amountCents / 100)}
          </output>
        </p>
        {residualDiffers ? <CompensationResidualFields form={form} residualMode={residualMode} /> : null}
        <FormField
          control={form.control}
          name="compensated_at"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('compensations.dialog.date')}</FormLabel>
              <FormControl>
                <Input type="date" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="note"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('compensations.dialog.note')}</FormLabel>
              <FormControl>
                <Textarea rows={2} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            {t('compensations.dialog.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {t(isPending ? 'compensations.dialog.saving' : 'compensations.dialog.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface CompensationDialogProps {
  /** What to compensate; `null` closes the dialog. */
  target: CompensationTarget | null
  onClose: () => void
}

/** "Compensa": closes one open receivable and one open payable of the same registry and company (spec 0364). */
export function CompensationDialog({ target, onClose }: CompensationDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={target !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="sm" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('compensations.dialog.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('compensations.dialog.description')}</DialogDescription>
        </DialogHeader>
        {target ? (
          <CompensationForm
            key={`${target.registryId}-${target.receivableId ?? 0}-${target.payableId ?? 0}`}
            target={target}
            onClose={onClose}
          />
        ) : null}
      </DialogContent>
    </Dialog>
  )
}
