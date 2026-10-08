import { useId, useMemo } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
  buildContractPaymentPayload,
  buildContractPaymentSchema,
  paymentFormDefaults,
  type ContractPaymentFormValues,
} from '@/features/work-order-contract-data/contract-payment-schema'
import { PaymentStatusLabel } from '@/features/work-order-contract-data/payment-status-label'
import {
  usePaymentStatusOptions,
  useUpdateContractDataLine,
} from '@/features/work-order-contract-data/use-work-order-contract-data'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

/** Select value standing for "no status": Radix items cannot carry an empty value. */
const NO_STATUS_VALUE = 'none'

/** First server-side message for the dialog, else the generic one. */
function serverMessage(error: AxiosError<ApiErrorResponse>, fallback: string): string {
  const fieldErrors = error.response?.data?.errors
  const first = fieldErrors ? Object.values(fieldErrors)[0]?.[0] : undefined
  return first ?? error.response?.data?.message ?? fallback
}

interface ContractPaymentDialogProps {
  workOrderId: number
  line: ContractDataLine
  /** Closes the dialog: after a saved "Save", an unchanged "Save" or "Cancel". */
  onClose: () => void
}

/**
 * "Line payment" dialog (spec 0201 D-12): status, agreement and unpaid flag of
 * one line. "Save" PATCHes only what changed (nothing changed = no request) and
 * closes on success; a refused save keeps the dialog open with the server message.
 */
export function ContractPaymentDialog({ workOrderId, line, onClose }: ContractPaymentDialogProps) {
  const { t } = useTranslation()
  const fieldId = useId()
  const options = usePaymentStatusOptions(line.payment.status)
  const mutation = useUpdateContractDataLine(workOrderId)
  const schema = useMemo(() => buildContractPaymentSchema(t), [t])
  const form = useForm<ContractPaymentFormValues>({
    resolver: zodResolver(schema),
    defaultValues: paymentFormDefaults(line),
  })
  const agreementError = form.formState.errors.payment_agreement?.message
  const errorId = `${fieldId}-agreement-error`

  const submit = form.handleSubmit((values) => {
    const payload = buildContractPaymentPayload(values, line)
    if (Object.keys(payload).length === 0) {
      onClose()
      return
    }
    mutation.mutate({ quoteLineId: line.quote_line_id, payload }, { onSuccess: onClose })
  })

  return (
    <Dialog open onOpenChange={(next) => {
        if (!next && !mutation.isPending) onClose()
      }}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle className="text-base">
            {t('workOrders.contractData.editor.title', { product: `${line.product.code} ${line.product.name}` })}
          </DialogTitle>
          <DialogDescription className="text-xs">{t('workOrders.contractData.editor.description')}</DialogDescription>
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
              name="work_order_payment_status_id"
              render={({ field }) => (
                <Select
                  value={field.value === null ? NO_STATUS_VALUE : String(field.value)}
                  onValueChange={(next) => field.onChange(next === NO_STATUS_VALUE ? null : Number(next))}
                  disabled={mutation.isPending}
                >
                  <SelectTrigger id={`${fieldId}-status`} size="sm" className="w-full text-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
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
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor={`${fieldId}-agreement`} className="text-xs">
              {t('workOrders.contractData.editor.agreement')}
            </Label>
            <Textarea
              id={`${fieldId}-agreement`}
              rows={3}
              className="min-h-0 py-1.5 text-xs md:text-xs"
              aria-invalid={agreementError ? true : undefined}
              aria-describedby={agreementError ? errorId : undefined}
              disabled={mutation.isPending}
              {...form.register('payment_agreement')}
            />
            {agreementError ? (
              <span id={errorId} role="alert" className="text-[11px] text-destructive">
                {agreementError}
              </span>
            ) : null}
          </div>

          <div className="flex items-center gap-2">
            <Controller
              control={form.control}
              name="has_unpaid"
              render={({ field }) => (
                <Switch
                  id={`${fieldId}-unpaid`}
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  disabled={mutation.isPending}
                />
              )}
            />
            <Label htmlFor={`${fieldId}-unpaid`} className="text-xs">
              {t('workOrders.contractData.editor.unpaid')}
            </Label>
          </div>

          {mutation.isError ? (
            <p role="alert" className="text-xs text-destructive">
              {serverMessage(mutation.error, t('workOrders.contractData.editor.saveError'))}
            </p>
          ) : null}

          <DialogFooter>
            <Button type="button" variant="outline" size="sm" className="bg-card" disabled={mutation.isPending} onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" size="sm" disabled={mutation.isPending}>
              {t('workOrders.contractData.editor.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
