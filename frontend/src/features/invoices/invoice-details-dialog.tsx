import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { InvoiceLayoutField } from '@/features/invoices/invoice-layout-field'
import { parseNumberInput } from '@/features/invoices/invoice-format'
import { useInvoiceDetailsForm } from '@/features/invoices/use-invoice-details-form'
import { useInvoice } from '@/features/invoices/use-invoice-queries'
import { INVOICE_TAGS, type Invoice, type InvoiceTag } from '@/features/invoices/types'

const NO_TAG = 'none'

function DetailsForm({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
  const { t } = useTranslation()
  const { form, submit, isPending } = useInvoiceDetailsForm(invoice, onClose)

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        <div className="grid gap-3 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="external_number"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('invoices.details.externalNumber')}</FormLabel>
                <FormControl>
                  <Input {...field} value={field.value ?? ''} onChange={(event) => field.onChange(event.target.value)} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="external_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('invoices.details.externalDate')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} value={field.value ?? ''} onChange={(event) => field.onChange(event.target.value)} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
        <p className="-mt-1 text-xs text-muted-foreground">{t('invoices.details.externalNumberHint')}</p>
        <div className="grid gap-3 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="tag"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('invoices.details.tag')}</FormLabel>
                <Select
                  value={field.value ?? NO_TAG}
                  onValueChange={(value) => field.onChange(value === NO_TAG ? null : (value as InvoiceTag))}
                >
                  <FormControl>
                    <SelectTrigger className="w-full bg-card">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value={NO_TAG}>{t('invoices.tags.none')}</SelectItem>
                    {INVOICE_TAGS.map((tag) => (
                      <SelectItem key={tag} value={tag}>
                        {t(`invoices.tags.${tag}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="deviation"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('invoices.details.deviation')}</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    step="0.01"
                    inputMode="decimal"
                    name={field.name}
                    ref={field.ref}
                    onBlur={field.onBlur}
                    value={field.value ?? ''}
                    onChange={(event) => field.onChange(parseNumberInput(event.target.value, null))}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
        <InvoiceLayoutField control={form.control} selected={invoice.layout} />
        <FormField
          control={form.control}
          name="internal_note"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('invoices.details.internalNote')}</FormLabel>
              <FormControl>
                <Textarea rows={3} {...field} value={field.value ?? ''} onChange={(event) => field.onChange(event.target.value)} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            {t('invoices.details.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {t(isPending ? 'invoices.details.saving' : 'invoices.details.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

function DetailsBody({ invoiceId, onClose }: { invoiceId: number; onClose: () => void }) {
  const { t } = useTranslation()
  const { data, isError, refetch } = useInvoice(invoiceId)

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2 p-4">
        <p className="text-sm text-destructive">{t('invoices.detail.loadError')}</p>
        <Button type="button" variant="secondary" size="sm" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!data) {
    return <Skeleton className="m-4 h-40" />
  }
  return <DetailsForm key={data.id} invoice={data} onClose={onClose} />
}

interface InvoiceDetailsDialogProps {
  invoiceId: number | null
  onClose: () => void
}

/** External number/date (proforma becomes invoice), tag, deviation and internal note. */
export function InvoiceDetailsDialog({ invoiceId, onClose }: InvoiceDetailsDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={invoiceId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('invoices.details.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('invoices.details.description')}</DialogDescription>
        </DialogHeader>
        {invoiceId !== null ? <DetailsBody invoiceId={invoiceId} onClose={onClose} /> : null}
      </DialogContent>
    </Dialog>
  )
}
