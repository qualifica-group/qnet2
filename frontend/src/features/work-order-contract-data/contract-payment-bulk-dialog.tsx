import { useId, useMemo } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  BULK_PAYMENT_DEFAULTS,
  KEEP_VALUE,
  NO_STATUS_VALUE,
  buildBulkPaymentPayload,
  buildBulkPaymentSchema,
  type BulkPaymentFormValues,
} from '@/features/work-order-contract-data/contract-payment-bulk-schema'
import { paymentServerMessage } from '@/features/work-order-contract-data/contract-payment-errors'
import { PaymentStatusLabel } from '@/features/work-order-contract-data/payment-status-label'
import {
  useBulkUpdateContractDataLines,
  usePaymentStatusOptions,
} from '@/features/work-order-contract-data/use-work-order-contract-data'

interface ContractPaymentBulkDialogProps {
  workOrderId: number
  quoteLineIds: number[]
  onClose: () => void
  /** After a saved "Apply": the caller closes the dialog and clears the selection. */
  onSuccess: () => void
}

/**
 * Bulk "line payment" dialog (spec 0333): the same status and/or unpaid flag
 * on every selected line. Each field starts on "Keep unchanged" and is sent
 * only when set; a refused save keeps the dialog open with the server message.
 */
export function ContractPaymentBulkDialog({ workOrderId, quoteLineIds, onClose, onSuccess }: ContractPaymentBulkDialogProps) {
  const { t } = useTranslation()
  const fieldId = useId()
  const options = usePaymentStatusOptions(null)
  const mutation = useBulkUpdateContractDataLines(workOrderId)
  const schema = useMemo(() => buildBulkPaymentSchema(t), [t])
  const form = useForm<BulkPaymentFormValues>({ resolver: zodResolver(schema), defaultValues: BULK_PAYMENT_DEFAULTS })
  const statusError = form.formState.errors.status?.message
  const errorId = `${fieldId}-status-error`
  const count = quoteLineIds.length

  const submit = form.handleSubmit((values) => {
    mutation.mutate(buildBulkPaymentPayload(values, quoteLineIds), {
      onSuccess: () => {
        toast.success(t('workOrders.contractData.bulk.saved', { count }))
        onSuccess()
      },
    })
  })

  return (
    <Dialog open onOpenChange={(next) => {
        if (!next && !mutation.isPending) onClose()
      }}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle className="text-base">{t('workOrders.contractData.bulk.title', { count })}</DialogTitle>
          <DialogDescription className="text-xs">{t('workOrders.contractData.bulk.description')}</DialogDescription>
        </DialogHeader>

        <form
          className="flex flex-col gap-3"
          onSubmit={(event) => {
            event.preventDefault()
            void submit()
          }}
        >
          <div className="flex flex-col gap-1.5">
            <Label htmlFor={`${fieldId}-status`} className="text-xs">
              {t('workOrders.contractData.editor.status')}
            </Label>
            <Controller
              control={form.control}
              name="status"
              render={({ field }) => (
                <Select value={field.value} onValueChange={field.onChange} disabled={mutation.isPending}>
                  <SelectTrigger
                    id={`${fieldId}-status`}
                    size="sm"
                    className="w-full text-xs"
                    aria-invalid={statusError ? true : undefined}
                    aria-describedby={statusError ? errorId : undefined}
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={KEEP_VALUE}>{t('workOrders.contractData.bulk.keep')}</SelectItem>
                    <SelectItem value={NO_STATUS_VALUE}>{t('workOrders.contractData.editor.noStatus')}</SelectItem>
                    {options.map((option) => (
                      <SelectItem key={option.id} value={String(option.id)}>
                        <PaymentStatusLabel status={option} />
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
            {statusError ? (
              <span id={errorId} role="alert" className="text-[11px] text-destructive">
                {statusError}
              </span>
            ) : null}
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor={`${fieldId}-unpaid`} className="text-xs">
              {t('workOrders.contractData.editor.unpaid')}
            </Label>
            <Controller
              control={form.control}
              name="unpaid"
              render={({ field }) => (
                <Select value={field.value} onValueChange={field.onChange} disabled={mutation.isPending}>
                  <SelectTrigger id={`${fieldId}-unpaid`} size="sm" className="w-full text-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={KEEP_VALUE}>{t('workOrders.contractData.bulk.keep')}</SelectItem>
                    <SelectItem value="yes">{t('workOrders.contractData.bulk.unpaidYes')}</SelectItem>
                    <SelectItem value="no">{t('workOrders.contractData.bulk.unpaidNo')}</SelectItem>
                  </SelectContent>
                </Select>
              )}
            />
          </div>

          {mutation.isError ? (
            <p role="alert" className="text-xs text-destructive">
              {paymentServerMessage(mutation.error, t('workOrders.contractData.editor.saveError'))}
            </p>
          ) : null}

          <DialogFooter>
            <Button type="button" variant="outline" size="sm" className="bg-card" disabled={mutation.isPending} onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" size="sm" disabled={mutation.isPending}>
              {t('workOrders.contractData.bulk.apply')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
