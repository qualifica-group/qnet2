import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { PackageCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import type { ReceiptFormValues } from '@/features/purchase-orders/purchase-order-receipt-schema'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import { usePurchaseOrderReceiptForm } from '@/features/purchase-orders/use-purchase-order-receipt-form'

type HeaderName = 'receipt_date' | 'delivery_note_number' | 'delivery_note_date'

interface HeaderInputProps {
  control: Control<ReceiptFormValues>
  name: HeaderName
  type: 'date' | 'text'
  label: string
  required?: boolean
}

function HeaderInput({ control, name, type, label, required }: HeaderInputProps) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs" required={required}>
            {label}
          </FormLabel>
          <FormControl>
            <Input type={type} className="h-8 text-xs" {...field} />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

interface PurchaseOrderReceiptDialogProps {
  order: PurchaseOrder
  onOpenChange: (open: boolean) => void
}

/**
 * "Registra ricezione" (spec 0254 D-1): date, delivery note, notes and one row
 * per open line with the quantity prefilled to the residual (0 skips the line,
 * not above the residual). Mounted only while open, so each opening restarts
 * from the current residuals.
 */
export function PurchaseOrderReceiptDialog({ order, onOpenChange }: PurchaseOrderReceiptDialogProps) {
  const { t } = useTranslation()
  const { form, fields, formErrors, rowsError, receiveAll, submit, isSaving } = usePurchaseOrderReceiptForm(
    order,
    () => onOpenChange(false),
  )
  const { control } = form

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent size="lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <PackageCheck className="size-4" aria-hidden="true" />
            {t('purchaseOrderActions.receipts.dialog.title')}
          </DialogTitle>
          <DialogDescription>{t('purchaseOrderActions.receipts.dialog.subtitle', { code: order.code })}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form noValidate onSubmit={submit} className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto">
            {formErrors.length > 0 ? (
              <ul role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                {formErrors.map((message) => (
                  <li key={message}>{message}</li>
                ))}
              </ul>
            ) : null}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <HeaderInput control={control} name="receipt_date" type="date" required label={t('purchaseOrderActions.receipts.fields.receiptDate')} />
              <HeaderInput control={control} name="delivery_note_number" type="text" label={t('purchaseOrderActions.receipts.fields.deliveryNoteNumber')} />
              <HeaderInput control={control} name="delivery_note_date" type="date" label={t('purchaseOrderActions.receipts.fields.deliveryNoteDate')} />
            </div>
            <FormField
              control={control}
              name="notes"
              render={({ field }) => (
                <FormItem className="gap-1">
                  <FormLabel className="text-xs">{t('purchaseOrderActions.receipts.fields.notes')}</FormLabel>
                  <FormControl>
                    <Textarea rows={2} className="min-h-0 text-xs" {...field} />
                  </FormControl>
                  <FormMessage className="text-xs" />
                </FormItem>
              )}
            />
            <div className="flex items-center justify-between gap-2">
              <h3 className="text-sm font-semibold">{t('purchaseOrderActions.receipts.dialog.lines')}</h3>
              <Button type="button" variant="outline" size="xs" className="bg-card" onClick={receiveAll}>
                {t('purchaseOrderActions.receipts.receiveAll')}
              </Button>
            </div>
            {rowsError ? (
              <p role="alert" className="text-xs text-destructive">
                {rowsError}
              </p>
            ) : null}
            <ul className="flex flex-col gap-1.5">
              {fields.map((row, index) => (
                <li key={row.id} className="grid grid-cols-[1fr_auto] items-start gap-2 rounded-md border bg-card p-1.5 sm:grid-cols-[1fr_8rem_9rem]">
                  <span className="truncate pt-1.5 text-xs" title={row.description}>
                    {row.position}. {row.description}
                  </span>
                  <span className="hidden pt-1.5 text-right text-xs text-muted-foreground sm:block">
                    {t('purchaseOrderActions.receipts.residual', { quantity: Number(row.residual).toFixed(3) })}
                  </span>
                  <FormField
                    control={control}
                    name={`rows.${index}.quantity`}
                    render={({ field }) => (
                      <FormItem className="gap-1">
                        <FormControl>
                          <Input
                            type="number"
                            step="0.001"
                            min="0"
                            inputMode="decimal"
                            className="h-8 text-right text-xs"
                            aria-label={t('purchaseOrderActions.receipts.quantityOf', { position: row.position })}
                            {...field}
                          />
                        </FormControl>
                        <FormMessage className="text-xs" />
                      </FormItem>
                    )}
                  />
                </li>
              ))}
            </ul>
            <DialogFooter>
              <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={isSaving}>
                {t('purchaseOrderActions.receipts.confirm')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
